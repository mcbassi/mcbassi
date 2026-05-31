<?php
declare(strict_types=1);

namespace App\Clientes;

use App\Auth\AuthService;
use App\Infra\Database;
use App\Infra\Env;
use App\Support\Request;
use App\Support\View;

final class ClienteController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Database $database,
        private readonly Request $request,
    ) {}

    // ── GET /clientes ─────────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireAuth();

        $db   = $this->database->pdo();
        $rows = $db->query("
            SELECT id, nome, email, cpf, telefone, plano, valor_plano,
                   status, DATE_FORMAT(proximo_vencimento,'%d/%m/%Y') AS vencimento,
                   DATE_FORMAT(data_cadastro,'%d/%m/%Y %H:%i') AS cadastrado_em
            FROM clientes
            ORDER BY nome
        ")->fetchAll();

        View::render('Clientes/index', [
            'pageTitle'    => 'Clientes',
            'contentTitle' => 'Clientes Cadastrados',
            'subtitle'     => 'Faturamento',
            'clientes'     => $rows,
        ]);
    }

    // ── GET /clientes/cadastro ────────────────────────────────────
    public function cadastro(): void
    {
        $this->auth->requireAuth();

        View::render('Clientes/cadastro', [
            'pageTitle'    => 'Cadastro de Cliente',
            'contentTitle' => 'Novo Cliente',
            'subtitle'     => 'Faturamento',
            'resultado'    => null,
            'erro'         => null,
            'documentos'   => $this->loadDocumentOptions(),
        ]);
    }

    // ── POST /clientes/cadastro ───────────────────────────────────
    public function cadastroSalvar(): void
    {
        $this->auth->requireAuth();

        // CSRF
        $token = (string) ($this->request->input('_csrf') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!check_csrf($token)) {
            $this->renderCadastro(null, 'Token de segurança inválido. Recarregue a página.');
            return;
        }

        // Lê campos
        $nome      = trim((string) ($this->request->input('nome')      ?? ''));
        $email     = trim((string) ($this->request->input('email')     ?? ''));
        $paisCodigo = strtoupper(trim((string) ($this->request->input('pais_codigo') ?? '')));
        $documentoTipo = strtoupper(trim((string) ($this->request->input('documento_tipo') ?? '')));
        $documento = $this->normalizeDocumentNumber((string) ($this->request->input('documento_numero') ?? ($this->request->input('cpf') ?? '')));
        $telefoneRaw = trim((string) ($this->request->input('telefone') ?? ''));
        $telefone  = preg_replace('/\D/', '', $telefoneRaw);
        $plano     = trim((string) ($this->request->input('plano')     ?? ''));
        $cardToken = trim((string) ($this->request->input('cardToken') ?? ''));
        $pmId      = trim((string) ($this->request->input('paymentMethodId') ?? ''));
        $issuer    = trim((string) ($this->request->input('issuerId')  ?? ''));
        $documentoConfig = $this->findDocumentConfig($paisCodigo, $documentoTipo);
        if ($documentoConfig === null) {
            $documentoConfig = $this->defaultDocumentConfig();
            if ($documentoConfig === null) {
                $this->renderCadastro(null, 'Selecione um pais e tipo de documento valido.');
                return;
            }
        }
        $paisCodigo = (string) $documentoConfig['pais_codigo'];
        $documentoTipo = (string) $documentoConfig['documento_tipo'];
        $docType = (string) $documentoConfig['mp_identification_type'];
        $mpAmountMultiplier = (float) $documentoConfig['amount_multiplier'];
        $phonePrefix = preg_replace('/\D/', '', (string) ($documentoConfig['phone_prefix'] ?? ''));
        if ($phonePrefix !== '' && str_starts_with($telefone, $phonePrefix)) {
            $telefoneNacional = substr($telefone, strlen($phonePrefix));
        } else {
            $telefoneNacional = $telefone;
        }
        $cardholderSame = (string) ($this->request->input('cardholder_same') ?? '1') === '1';
        $cardholderDocumentoTipo = $cardholderSame
            ? $documentoTipo
            : strtoupper(trim((string) ($this->request->input('cardholder_documento_tipo') ?? $documentoTipo)));
        $cardholderDocumento = $cardholderSame
            ? $documento
            : $this->normalizeDocumentNumber((string) ($this->request->input('cardholder_documento_numero') ?? ''));
        $cardholderConfig = $this->findDocumentConfig($paisCodigo, $cardholderDocumentoTipo) ?? $documentoConfig;
        $cardholderDocType = (string) $cardholderConfig['mp_identification_type'];

        // Validações
        if ($nome === '')         { $this->renderCadastro(null, 'Nome é obrigatório.'); return; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->renderCadastro(null, 'E-mail inválido.'); return; }
        if ($documento === '')  { $this->renderCadastro(null, 'Documento invalido.'); return; }
        $documentError = $this->validateDocumentNumber($docType, $documento);
        if ($documentError !== null) { $this->renderCadastro(null, $documentError); return; }
        $cardholderDocumentError = $this->validateDocumentNumber($cardholderDocType, $cardholderDocumento);
        if ($cardholderDocumentError !== null) { $this->renderCadastro(null, 'Documento do titular do cartao invalido: ' . $cardholderDocumentError); return; }
        if (strlen($telefoneNacional) < 7) { $this->renderCadastro(null, 'Telefone inválido.'); return; }
        if ($cardToken === '')    { $this->renderCadastro(null, 'Dados do cartão ausentes. Tente novamente.'); return; }

        $planos = [
            'basico'       => ['nome' => 'Básico',       'valor' => 49.90],
            'profissional' => ['nome' => 'Profissional', 'valor' => 99.90],
            'enterprise'   => ['nome' => 'Enterprise',   'valor' => 249.90],
        ];
        if (!isset($planos[$plano])) { $this->renderCadastro(null, 'Plano inválido.'); return; }
        $planoInfo = $planos[$plano];
        $mpTransactionAmount = round((float) $planoInfo['valor'] * $mpAmountMultiplier, 2);

        // ── Mercado Pago ──────────────────────────────────────────
        $mpKey = $this->mercadoPagoAccessToken();
        if ($mpKey === '') {
            $this->renderCadastro(null, 'Access Token do Mercado Pago nÃ£o configurado em MP_ACCESS_TOKEN.');
            return;
        }

        $nomePartes = preg_split('/\s+/', $nome, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = $nomePartes[0] ?? $nome;
        $lastName = trim(implode(' ', array_slice($nomePartes, 1)));
        if (mb_strlen($lastName) < 2) {
            $lastName = 'Cliente';
        }

        $mpCustomer = $this->mpPost('/v1/customers', [
            'email'          => $email,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'identification' => ['type' => $docType, 'number' => $documento],
            'phone'          => ['area_code' => substr($telefoneNacional, 0, 2), 'number' => substr($telefoneNacional, 2)],
        ], $mpKey);

        if (isset($mpCustomer['error'])) {
            if ($this->isMpCustomerAlreadyExists($mpCustomer)) {
                $mpCustomer = $this->findMpCustomerByEmail($email, $mpKey);
                if (!isset($mpCustomer['id'])) {
                    $this->renderCadastro(null, 'Cliente ja existe no Mercado Pago, mas nao foi possivel recuperar o cadastro existente: ' . $this->formatMpError($mpCustomer));
                    return;
                }
            } else {
                $this->renderCadastro(null, 'Erro ao criar cliente no Mercado Pago: ' . $this->formatMpError($mpCustomer));
                return;
            }
        }

        $mpCard = $this->mpPost("/v1/customers/{$mpCustomer['id']}/cards", ['token' => $cardToken], $mpKey);
        $cardSaved = !isset($mpCard['error']);

        $paymentPayload = [
            'transaction_amount' => $mpTransactionAmount,
            'description'        => "Assinatura {$planoInfo['nome']} - {$nome}",
            'payment_method_id'  => $pmId,
            'issuer_id'          => $issuer ?: null,
            'installments'       => 1,
        ];

        if ($cardSaved) {
            $paymentPayload['payer'] = ['type' => 'customer', 'id' => $mpCustomer['id']];
        } else {
            $paymentPayload['token'] = $cardToken;
            $paymentPayload['payer'] = [
                'email' => $email,
                'identification' => ['type' => $cardholderDocType, 'number' => $cardholderDocumento],
            ];
        }

        $pagamento = $this->mpPost('/v1/payments', $paymentPayload, $mpKey, $this->makeIdempotencyKey($email));

        $statusOk = ['approved', 'pending', 'in_process'];
        if (!in_array($pagamento['status'] ?? '', $statusOk, true)) {
            $msg = isset($pagamento['error'])
                ? $this->formatMpError($pagamento)
                : ($pagamento['status_detail'] ?? ($pagamento['message'] ?? 'Erro desconhecido'));
            $this->renderCadastro(null, 'Pagamento nao aprovado: ' . $msg);
            return;
        }

        // ── Salva no banco ────────────────────────────────────────
        try {
            $pdo  = $this->database->pdo();
            $stmt = $pdo->prepare("
                INSERT INTO clientes
                    (nome, email, cpf, telefone, pais_codigo, documento_tipo, plano, valor_plano,
                     mp_customer_id, mp_card_id, mp_payment_method_id, mp_issuer_id,
                     status, data_cadastro, proximo_vencimento)
                VALUES
                    (:nome, :email, :cpf, :telefone, :pais_codigo, :documento_tipo, :plano, :valor,
                     :mp_cid, :mp_card, :pm_id, :issuer,
                     'ativo', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))
                ON DUPLICATE KEY UPDATE
                    nome = VALUES(nome),
                    cpf = VALUES(cpf),
                    telefone = VALUES(telefone),
                    pais_codigo = VALUES(pais_codigo),
                    documento_tipo = VALUES(documento_tipo),
                    plano = VALUES(plano),
                    valor_plano = VALUES(valor_plano),
                    mp_customer_id = VALUES(mp_customer_id),
                    mp_card_id = VALUES(mp_card_id),
                    mp_payment_method_id = VALUES(mp_payment_method_id),
                    mp_issuer_id = VALUES(mp_issuer_id),
                    status = 'ativo',
                    proximo_vencimento = DATE_ADD(NOW(), INTERVAL 1 MONTH),
                    updated_at = NOW()
            ");
            $stmt->execute([
                ':nome'     => $nome,
                ':email'    => $email,
                ':cpf'      => $documento,
                ':telefone' => $telefone,
                ':pais_codigo' => $paisCodigo,
                ':documento_tipo' => $documentoTipo,
                ':plano'    => $plano,
                ':valor'    => $planoInfo['valor'],
                ':mp_cid'   => $mpCustomer['id'],
                ':mp_card'  => $cardSaved ? $mpCard['id'] : (string) ($pagamento['card']['id'] ?? 'token_payment'),
                ':pm_id'    => $pmId,
                ':issuer'   => $issuer ?: null,
            ]);
        } catch (\Throwable $e) {
            $this->renderCadastro(null, 'Erro ao salvar no banco: ' . $e->getMessage());
            return;
        }

        $this->renderCadastro([
            'cliente' => $nome,
            'plano'   => $planoInfo['nome'],
            'valor'   => $planoInfo['valor'],
            'status'  => $pagamento['status'],
        ], null);
    }

    // ── helpers ───────────────────────────────────────────────────
    private function renderCadastro(?array $resultado, ?string $erro): void
    {
        View::render('Clientes/cadastro', [
            'pageTitle'    => 'Cadastro de Cliente',
            'contentTitle' => 'Novo Cliente',
            'subtitle'     => 'Faturamento',
            'resultado'    => $resultado,
            'erro'         => $erro,
            'documentos'   => $this->loadDocumentOptions(),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function loadDocumentOptions(): array
    {
        try {
            $stmt = $this->database->pdo()->query("
                SELECT pais_codigo, pais_nome, documento_tipo, documento_nome,
                       mp_identification_type, placeholder, max_length,
                       phone_prefix, amount_multiplier, is_default
                FROM pais_documentos
                WHERE ativo = 1
                ORDER BY pais_nome, is_default DESC, documento_nome
            ");
            $rows = $stmt->fetchAll();
            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return [
                [
                    'pais_codigo' => 'BR',
                    'pais_nome' => 'Brasil',
                    'documento_tipo' => 'CPF',
                    'documento_nome' => 'CPF',
                    'mp_identification_type' => 'CPF',
                    'placeholder' => '000.000.000-00',
                    'max_length' => 14,
                    'phone_prefix' => '+55',
                    'amount_multiplier' => 1,
                    'is_default' => 1,
                ],
                [
                    'pais_codigo' => 'CO',
                    'pais_nome' => 'Colombia',
                    'documento_tipo' => 'CC',
                    'documento_nome' => 'Cedula de Ciudadania',
                    'mp_identification_type' => 'CC',
                    'placeholder' => 'Numero de cedula',
                    'max_length' => 20,
                    'phone_prefix' => '+57',
                    'amount_multiplier' => 100,
                    'is_default' => 1,
                ],
            ];
        }
    }

    private function findDocumentConfig(string $paisCodigo, string $documentoTipo): ?array
    {
        $paisCodigo = strtoupper(trim($paisCodigo));
        $documentoTipo = strtoupper(trim($documentoTipo));
        foreach ($this->loadDocumentOptions() as $documento) {
            if (
                strtoupper((string) $documento['pais_codigo']) === $paisCodigo
                && strtoupper((string) $documento['documento_tipo']) === $documentoTipo
            ) {
                return $documento;
            }
        }

        return null;
    }

    private function defaultDocumentConfig(): ?array
    {
        $envMpType = strtoupper(trim((string) Env::get('MP_IDENTIFICATION_TYPE', '')));
        $fallback = null;

        foreach ($this->loadDocumentOptions() as $documento) {
            if ($envMpType !== '' && strtoupper((string) $documento['mp_identification_type']) === $envMpType) {
                return $documento;
            }

            if ($fallback === null && (int) ($documento['is_default'] ?? 0) === 1) {
                $fallback = $documento;
            }
        }

        return $fallback;
    }

    private function normalizeDocumentNumber(string $documento): string
    {
        return strtoupper(preg_replace('/[^\dA-Za-z]/', '', $documento) ?? '');
    }

    private function validateDocumentNumber(string $type, string $number): ?string
    {
        $type = strtoupper($type);
        if ($number === '') {
            return 'Documento invalido.';
        }

        return match ($type) {
            'CPF' => $this->isValidCpf($number) ? null : 'CPF invalido.',
            'CNPJ' => $this->isValidCnpj($number) ? null : 'CNPJ invalido.',
            'CC' => preg_match('/^\d{5,12}$/', $number) === 1 ? null : 'Cedula de Ciudadania deve ter de 5 a 12 digitos.',
            'NIT' => preg_match('/^\d{8,15}$/', $number) === 1 ? null : 'NIT deve ter de 8 a 15 digitos.',
            'CE' => preg_match('/^[A-Z0-9]{5,12}$/', $number) === 1 ? null : 'Cedula de Extranjeria deve ter de 5 a 12 caracteres.',
            'TI' => preg_match('/^\d{5,12}$/', $number) === 1 ? null : 'Tarjeta de Identidad deve ter de 5 a 12 digitos.',
            'PAS' => preg_match('/^[A-Z0-9]{5,20}$/', $number) === 1 ? null : 'Passaporte deve ter de 5 a 20 caracteres.',
            default => strlen($number) >= 4 ? null : 'Documento deve ter ao menos 4 caracteres.',
        };
    }

    private function isValidCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (!preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        for ($digitIndex = 12; $digitIndex <= 13; $digitIndex++) {
            $sum = 0;
            foreach ($weights[$digitIndex - 12] as $i => $weight) {
                $sum += (int) $cnpj[$i] * $weight;
            }
            $rest = $sum % 11;
            $digit = $rest < 2 ? 0 : 11 - $rest;
            if ((int) $cnpj[$digitIndex] !== $digit) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $body */
    private function mpPost(string $endpoint, array $body, string $token, ?string $idempotencyKey = null): array
    {
        $ch = curl_init("https://api.mercadopago.com{$endpoint}");
        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer {$token}",
        ];
        if ($idempotencyKey !== null) {
            $headers[] = "X-Idempotency-Key: {$idempotencyKey}";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_PROXY          => '',
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $raw = (string) curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $resp = json_decode($raw, true);
        if (!is_array($resp)) {
            return [
                'error' => true,
                'status' => $httpCode,
                'message' => $curlError !== '' ? $curlError : 'Sem resposta da API',
            ];
        }

        if ($httpCode >= 400 && !isset($resp['status'])) {
            $resp['status'] = $httpCode;
        }

        return $resp;
    }

    private function makeIdempotencyKey(string $email): string
    {
        return 'cliente-' . hash('sha256', $email . '|' . microtime(true) . '|' . random_int(100000, 999999));
    }

    private function mpGet(string $endpoint, string $token): array
    {
        $ch = curl_init("https://api.mercadopago.com{$endpoint}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_PROXY          => '',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$token}",
            ],
        ]);
        $raw = (string) curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $resp = json_decode($raw, true);
        if (!is_array($resp)) {
            return [
                'error' => true,
                'status' => $httpCode,
                'message' => $curlError !== '' ? $curlError : 'Sem resposta da API',
            ];
        }

        if ($httpCode >= 400 && !isset($resp['status'])) {
            $resp['status'] = $httpCode;
        }

        return $resp;
    }

    /** @param array<string,mixed> $error */
    private function isMpCustomerAlreadyExists(array $error): bool
    {
        if (($error['status'] ?? null) !== 400) {
            return false;
        }

        if (isset($error['cause']) && is_array($error['cause'])) {
            foreach ($error['cause'] as $cause) {
                if (is_array($cause) && (string) ($cause['code'] ?? '') === '101') {
                    return true;
                }
            }
        }

        return stripos((string) ($error['message'] ?? ''), 'already exist') !== false;
    }

    private function findMpCustomerByEmail(string $email, string $token): array
    {
        $response = $this->mpGet('/v1/customers/search?email=' . rawurlencode($email), $token);
        if (isset($response['error'])) {
            return $response;
        }

        $results = $response['results'] ?? [];
        if (!is_array($results) || $results === []) {
            return ['error' => true, 'message' => 'Customer existente nao encontrado na busca.', 'status' => $response['status'] ?? 404];
        }

        foreach ($results as $customer) {
            if (is_array($customer) && strcasecmp((string) ($customer['email'] ?? ''), $email) === 0) {
                return $customer;
            }
        }

        return is_array($results[0] ?? null)
            ? $results[0]
            : ['error' => true, 'message' => 'Resposta invalida da busca de customer.'];
    }

    /** @param array<string,mixed> $error */
    private function formatMpError(array $error): string
    {
        $parts = [];
        if (isset($error['message']) && $error['message'] !== '') {
            $parts[] = (string) $error['message'];
        }
        if (isset($error['status'])) {
            $parts[] = 'HTTP ' . (string) $error['status'];
        }
        if (isset($error['cause']) && is_array($error['cause'])) {
            foreach ($error['cause'] as $cause) {
                if (!is_array($cause)) {
                    continue;
                }
                $description = (string) ($cause['description'] ?? '');
                $code = (string) ($cause['code'] ?? '');
                if ($description !== '') {
                    $parts[] = $code !== '' ? "{$description} ({$code})" : $description;
                }
            }
        }

        return implode(' - ', array_unique($parts)) ?: 'Erro desconhecido.';
    }

    private function mercadoPagoAccessToken(): string
    {
        if (defined('MP_ACCESS_TOKEN')) {
            return trim((string) MP_ACCESS_TOKEN);
        }

        return trim((string) Env::get('MP_ACCESS_TOKEN', ''));
    }
}
