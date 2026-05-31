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
        $cpf       = preg_replace('/\D/', '', (string) ($this->request->input('cpf') ?? ''));
        $telefone  = preg_replace('/\D/', '', (string) ($this->request->input('telefone') ?? ''));
        $plano     = trim((string) ($this->request->input('plano')     ?? ''));
        $cardToken = trim((string) ($this->request->input('cardToken') ?? ''));
        $pmId      = trim((string) ($this->request->input('paymentMethodId') ?? ''));
        $issuer    = trim((string) ($this->request->input('issuerId')  ?? ''));
        $docType   = strtoupper(trim((string) Env::get('MP_IDENTIFICATION_TYPE', 'CPF')));

        // Validações
        if ($nome === '')         { $this->renderCadastro(null, 'Nome é obrigatório.'); return; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->renderCadastro(null, 'E-mail inválido.'); return; }
        if (strlen($cpf) !== 11)  { $this->renderCadastro(null, 'CPF inválido.'); return; }
        if (strlen($telefone) < 10) { $this->renderCadastro(null, 'Telefone inválido.'); return; }
        if ($cardToken === '')    { $this->renderCadastro(null, 'Dados do cartão ausentes. Tente novamente.'); return; }

        $planos = [
            'basico'       => ['nome' => 'Básico',       'valor' => 49.90],
            'profissional' => ['nome' => 'Profissional', 'valor' => 99.90],
            'enterprise'   => ['nome' => 'Enterprise',   'valor' => 249.90],
        ];
        if (!isset($planos[$plano])) { $this->renderCadastro(null, 'Plano inválido.'); return; }
        $planoInfo = $planos[$plano];

        // ── Mercado Pago ──────────────────────────────────────────
        $mpKey = $this->mercadoPagoAccessToken();
        if ($mpKey === '') {
            $this->renderCadastro(null, 'Access Token do Mercado Pago nÃ£o configurado em MP_ACCESS_TOKEN.');
            return;
        }

        $mpCustomer = $this->mpPost('/v1/customers', [
            'email'          => $email,
            'first_name'     => explode(' ', $nome)[0],
            'last_name'      => implode(' ', array_slice(explode(' ', $nome), 1)) ?: '-',
            'identification' => ['type' => $docType, 'number' => $cpf],
            'phone'          => ['area_code' => substr($telefone, 0, 2), 'number' => substr($telefone, 2)],
        ], $mpKey);

        if (isset($mpCustomer['error'])) {
            $this->renderCadastro(null, 'Erro ao criar cliente no Mercado Pago: ' . $this->formatMpError($mpCustomer));
            return;
        }

        $mpCard = $this->mpPost("/v1/customers/{$mpCustomer['id']}/cards", ['token' => $cardToken], $mpKey);
        if (isset($mpCard['error'])) {
            $this->renderCadastro(null, 'Erro ao salvar cartao: ' . $this->formatMpError($mpCard));
            return;
        }

        $pagamento = $this->mpPost('/v1/payments', [
            'transaction_amount' => $planoInfo['valor'],
            'description'        => "Assinatura {$planoInfo['nome']} - {$nome}",
            'payment_method_id'  => $pmId,
            'issuer_id'          => $issuer ?: null,
            'installments'       => 1,
            'payer'              => ['type' => 'customer', 'id' => $mpCustomer['id']],
        ], $mpKey);

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
                    (nome, email, cpf, telefone, plano, valor_plano,
                     mp_customer_id, mp_card_id, mp_payment_method_id, mp_issuer_id,
                     status, data_cadastro, proximo_vencimento)
                VALUES
                    (:nome, :email, :cpf, :telefone, :plano, :valor,
                     :mp_cid, :mp_card, :pm_id, :issuer,
                     'ativo', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))
            ");
            $stmt->execute([
                ':nome'     => $nome,
                ':email'    => $email,
                ':cpf'      => $cpf,
                ':telefone' => $telefone,
                ':plano'    => $plano,
                ':valor'    => $planoInfo['valor'],
                ':mp_cid'   => $mpCustomer['id'],
                ':mp_card'  => $mpCard['id'],
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
        ]);
    }

    /** @param array<string,mixed> $body */
    private function mpPost(string $endpoint, array $body, string $token): array
    {
        $ch = curl_init("https://api.mercadopago.com{$endpoint}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
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
