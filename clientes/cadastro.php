<?php
/**
 * Cadastro de Clientes - Faturamento via Mercado Pago
 * 
 * CONFIGURAÇÃO:
 * 1. Substitua MP_PUBLIC_KEY pela sua chave pública do Mercado Pago
 * 2. Substitua MP_ACCESS_TOKEN pelo seu Access Token (somente no backend)
 * 3. Configure o banco de dados abaixo
 * 
 * INSTALAÇÃO:
 * composer require mercadopago/dx-php
 */

// ============================================================
// CONFIGURAÇÕES - Edite aqui
// ============================================================
define('MP_PUBLIC_KEY',   'TEST-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'); // Chave pública MP
define('MP_ACCESS_TOKEN', 'TEST-0000000000000000-000000-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-000000000'); // Somente backend

define('DB_HOST', 'localhost');
define('DB_NAME', 'saas_clientes');
define('DB_USER', 'root');
define('DB_PASS', '');

// ============================================================
// CONEXÃO COM BANCO
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

// ============================================================
// PROCESSAMENTO DO FORMULÁRIO (POST)
// ============================================================
$resultado = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação básica
    $campos = ['nome', 'email', 'cpf', 'telefone', 'plano', 'cardToken', 'paymentMethodId', 'installments', 'issuerId'];
    foreach ($campos as $campo) {
        if (empty($_POST[$campo])) {
            $erro = "Campo obrigatório ausente: $campo";
            break;
        }
    }

    if (!$erro) {
        $nome      = trim($_POST['nome']);
        $email     = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        $cpf       = preg_replace('/\D/', '', $_POST['cpf']);
        $telefone  = preg_replace('/\D/', '', $_POST['telefone']);
        $plano     = $_POST['plano'];
        $cardToken = $_POST['cardToken'];
        $pmId      = $_POST['paymentMethodId'];
        $issuer    = $_POST['issuerId'];

        if (!$email) { $erro = "E-mail inválido."; }
        if (strlen($cpf) !== 11) { $erro = "CPF inválido."; }
    }

    if (!$erro) {
        // Valores dos planos
        $planos = [
            'basico'       => ['nome' => 'Básico',       'valor' => 49.90],
            'profissional' => ['nome' => 'Profissional', 'valor' => 99.90],
            'enterprise'   => ['nome' => 'Enterprise',   'valor' => 249.90],
        ];
        $planoSelecionado = $planos[$plano] ?? null;
        if (!$planoSelecionado) { $erro = "Plano inválido."; }
    }

    if (!$erro) {
        // ---- 1. Criar Customer no Mercado Pago ----
        $mpCustomer = mpCriarCustomer($nome, $email, $cpf, $telefone);
        if (isset($mpCustomer['error'])) {
            $erro = "Erro ao criar cliente no MP: " . $mpCustomer['message'];
        }
    }

    if (!$erro) {
        // ---- 2. Associar cartão ao customer ----
        $mpCard = mpAssociarCartao($mpCustomer['id'], $cardToken);
        if (isset($mpCard['error'])) {
            $erro = "Erro ao salvar cartão: " . $mpCard['message'];
        }
    }

    if (!$erro) {
        // ---- 3. Cobrar primeira mensalidade ----
        $pagamento = mpCobrar(
            $planoSelecionado['valor'],
            $mpCustomer['id'],
            $mpCard['id'],
            $pmId,
            $issuer,
            "Assinatura {$planoSelecionado['nome']} - $nome"
        );
        if (isset($pagamento['error']) || !in_array($pagamento['status'] ?? '', ['approved', 'pending', 'in_process'])) {
            $erro = "Pagamento não aprovado: " . ($pagamento['status_detail'] ?? 'Erro desconhecido');
        }
    }

    if (!$erro) {
        // ---- 4. Salvar no banco ----
        try {
            $db = getDB();
            $stmt = $db->prepare("
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
                ':nome'    => $nome,
                ':email'   => $email,
                ':cpf'     => $cpf,
                ':telefone'=> $telefone,
                ':plano'   => $plano,
                ':valor'   => $planoSelecionado['valor'],
                ':mp_cid'  => $mpCustomer['id'],
                ':mp_card' => $mpCard['id'],
                ':pm_id'   => $pmId,
                ':issuer'  => $issuer,
            ]);

            $resultado = [
                'cliente'   => $nome,
                'plano'     => $planoSelecionado['nome'],
                'valor'     => $planoSelecionado['valor'],
                'pagamento' => $pagamento['status'],
                'id'        => $db->lastInsertId(),
            ];
        } catch (Exception $e) {
            $erro = "Erro ao salvar no banco: " . $e->getMessage();
        }
    }
}

// ============================================================
// FUNÇÕES MERCADO PAGO (via REST direto)
// ============================================================
function mpPost(string $url, array $body): array {
    $ch = curl_init("https://api.mercadopago.com$url");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        ],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $resp ?? ['error' => true, 'message' => 'Sem resposta da API'];
}

function mpCriarCustomer(string $nome, string $email, string $cpf, string $tel): array {
    return mpPost('/v1/customers', [
        'email'           => $email,
        'first_name'      => explode(' ', $nome)[0],
        'last_name'       => implode(' ', array_slice(explode(' ', $nome), 1)) ?: '-',
        'identification'  => ['type' => 'CPF', 'number' => $cpf],
        'phone'           => ['area_code' => substr($tel, 0, 2), 'number' => substr($tel, 2)],
    ]);
}

function mpAssociarCartao(string $customerId, string $token): array {
    return mpPost("/v1/customers/$customerId/cards", ['token' => $token]);
}

function mpCobrar(float $valor, string $custId, string $cardId, string $pmId, string $issuer, string $desc): array {
    return mpPost('/v1/payments', [
        'transaction_amount'  => $valor,
        'description'         => $desc,
        'payment_method_id'   => $pmId,
        'issuer_id'           => $issuer,
        'installments'        => 1,
        'payer'               => ['type' => 'customer', 'id' => $custId],
        'token'               => null, // usa saved card via customer
    ]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cadastro de Cliente — SaaS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://sdk.mercadopago.com/js/v2"></script>
<style>
/* ============================================================
   RESET & VARIÁVEIS
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:         #0a0c10;
    --surface:    #111318;
    --surface2:   #1a1d25;
    --border:     #2a2d38;
    --border-hi:  #3d4155;
    --accent:     #00e5a0;
    --accent-dim: #00b87d;
    --accent-glow:rgba(0,229,160,0.15);
    --red:        #ff4d6d;
    --text:       #e8eaf0;
    --muted:      #6b7080;
    --label:      #9ca3b0;
    --mono:       'JetBrains Mono', monospace;
    --sans:       'Sora', sans-serif;
    --radius:     12px;
    --radius-sm:  8px;
    --trans:      0.2s cubic-bezier(0.4,0,0.2,1);
}

body {
    font-family: var(--sans);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 40px 20px 80px;
    background-image:
        radial-gradient(ellipse 60% 40% at 20% 10%, rgba(0,229,160,0.06) 0%, transparent 60%),
        radial-gradient(ellipse 40% 30% at 80% 80%, rgba(0,100,255,0.05) 0%, transparent 60%);
}

/* ============================================================
   HEADER
   ============================================================ */
.header {
    width: 100%;
    max-width: 580px;
    margin-bottom: 36px;
}
.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 28px;
}
.logo-dot {
    width: 32px; height: 32px;
    background: var(--accent);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 14px; color: #000;
}
.logo-name {
    font-size: 18px; font-weight: 600; letter-spacing: -0.3px;
}
.logo-name span { color: var(--accent); }

.page-title {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.8px;
    line-height: 1.2;
    margin-bottom: 8px;
}
.page-sub {
    font-size: 14px;
    color: var(--muted);
    font-weight: 400;
}

/* ============================================================
   PLANOS
   ============================================================ */
.plans-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 28px;
}
.plan-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    cursor: pointer;
    transition: border-color var(--trans), background var(--trans), transform var(--trans);
    position: relative;
    text-align: center;
}
.plan-card:hover {
    border-color: var(--border-hi);
    transform: translateY(-1px);
}
.plan-card.selected {
    border-color: var(--accent);
    background: var(--accent-glow);
}
.plan-card.selected::after {
    content: '✓';
    position: absolute;
    top: 8px; right: 10px;
    font-size: 11px;
    color: var(--accent);
    font-weight: 700;
}
.plan-name {
    font-size: 12px;
    color: var(--label);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 6px;
}
.plan-price {
    font-family: var(--mono);
    font-size: 20px;
    font-weight: 500;
    color: var(--text);
}
.plan-price sup { font-size: 12px; vertical-align: super; color: var(--muted); }
.plan-period { font-size: 10px; color: var(--muted); margin-top: 2px; }
.plan-badge {
    display: inline-block;
    margin-top: 6px;
    padding: 2px 7px;
    background: var(--accent);
    color: #000;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

/* ============================================================
   CARD / FORM
   ============================================================ */
.card {
    width: 100%;
    max-width: 580px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 16px;
}

.section-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--accent);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field-row.single { grid-template-columns: 1fr; }
.field-row.three { grid-template-columns: 1fr 1fr 1fr; }

.field { margin-bottom: 14px; }
label {
    display: block;
    font-size: 12px;
    color: var(--label);
    font-weight: 500;
    margin-bottom: 6px;
    letter-spacing: 0.2px;
}
input, select {
    width: 100%;
    background: var(--surface2);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-family: var(--sans);
    font-size: 14px;
    padding: 10px 14px;
    outline: none;
    transition: border-color var(--trans), box-shadow var(--trans);
    -webkit-appearance: none;
}
input::placeholder { color: var(--muted); }
input:focus, select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}
select { cursor: pointer; }
select option { background: var(--surface2); }

/* Campos do cartão (injetados pelo SDK MP) */
.mp-field-wrapper {
    background: var(--surface2);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    height: 42px;
    display: flex;
    align-items: center;
    padding: 0 14px;
    transition: border-color var(--trans), box-shadow var(--trans);
}
.mp-field-wrapper.focused {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}
#cardNumberElement, #expirationDateElement, #securityCodeElement {
    width: 100%; height: 100%;
}

/* Bandeiras */
.card-icons {
    display: flex;
    gap: 6px;
    align-items: center;
    margin-bottom: 14px;
}
.card-icon {
    width: 36px; height: 24px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; color: var(--muted); font-weight: 600;
    transition: border-color var(--trans);
}
.card-icon.visa   { color: #1565c0; }
.card-icon.master { color: #eb001b; }
.card-icon.elo    { color: #ffd700; }
.card-icon.active {
    border-color: var(--accent);
    box-shadow: 0 0 0 2px var(--accent-glow);
}

/* Nome do titular (campo real) */
#cardholderName {
    text-transform: uppercase;
    letter-spacing: 1px;
    font-family: var(--mono);
}

/* ============================================================
   SEGURANÇA INFO
   ============================================================ */
.security-note {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(0,229,160,0.05);
    border: 1px solid rgba(0,229,160,0.15);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    font-size: 12px;
    color: var(--label);
    margin-top: 4px;
}
.security-note svg { flex-shrink: 0; }

/* ============================================================
   BOTÃO
   ============================================================ */
.btn-submit {
    width: 100%;
    max-width: 580px;
    padding: 16px;
    background: var(--accent);
    color: #000;
    font-family: var(--sans);
    font-weight: 700;
    font-size: 15px;
    letter-spacing: -0.2px;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    transition: background var(--trans), transform var(--trans), box-shadow var(--trans);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
}
.btn-submit:hover {
    background: #00f7b0;
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(0,229,160,0.3);
}
.btn-submit:active { transform: translateY(0); }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.btn-submit .spinner {
    width: 18px; height: 18px;
    border: 2px solid rgba(0,0,0,0.3);
    border-top-color: #000;
    border-radius: 50%;
    display: none;
    animation: spin 0.7s linear infinite;
}
.btn-submit.loading .spinner { display: block; }
.btn-submit.loading .btn-text { opacity: 0.6; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ============================================================
   ALERTS
   ============================================================ */
.alert {
    width: 100%;
    max-width: 580px;
    padding: 16px 20px;
    border-radius: var(--radius);
    font-size: 14px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: slideIn 0.3s ease;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.alert-success {
    background: rgba(0,229,160,0.08);
    border: 1px solid rgba(0,229,160,0.3);
    color: var(--accent);
}
.alert-error {
    background: rgba(255,77,109,0.08);
    border: 1px solid rgba(255,77,109,0.3);
    color: var(--red);
}
.alert-title { font-weight: 600; margin-bottom: 4px; }
.alert-body  { font-size: 13px; opacity: 0.8; }

/* ============================================================
   RODAPÉ
   ============================================================ */
.footer {
    margin-top: 32px;
    text-align: center;
    font-size: 12px;
    color: var(--muted);
}
.footer a { color: var(--label); text-decoration: none; }
.mp-logo {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 600;
    color: #009ee3;
    margin-top: 10px;
}
</style>
</head>
<body>

<?php if ($resultado): ?>
<!-- SUCESSO -->
<div class="alert alert-success" style="max-width:580px; margin-top: 0;">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div>
        <div class="alert-title">Cadastro realizado com sucesso! 🎉</div>
        <div class="alert-body">
            Cliente: <strong><?= htmlspecialchars($resultado['cliente']) ?></strong> |
            Plano: <strong><?= $resultado['plano'] ?></strong> |
            Valor: <strong>R$ <?= number_format($resultado['valor'], 2, ',', '.') ?>/mês</strong> |
            Pagamento: <strong><?= strtoupper($resultado['pagamento']) ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-error">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div>
        <div class="alert-title">Erro no cadastro</div>
        <div class="alert-body"><?= htmlspecialchars($erro) ?></div>
    </div>
</div>
<?php endif; ?>

<div class="header">
    <div class="logo">
        <div class="logo-dot">S</div>
        <div class="logo-name">Sua<span>App</span></div>
    </div>
    <div class="page-title">Cadastro de cliente</div>
    <div class="page-sub">Configure o plano e os dados de faturamento recorrente</div>
</div>

<!-- PLANOS -->
<div style="width:100%; max-width:580px; margin-bottom:0;">
    <div class="section-label" style="margin-bottom:14px;">Escolha o plano</div>
    <div class="plans-grid" id="plansGrid">
        <div class="plan-card" data-plano="basico" onclick="selecionarPlano(this)">
            <div class="plan-name">Básico</div>
            <div class="plan-price"><sup>R$</sup>49<small style="font-size:14px">,90</small></div>
            <div class="plan-period">por mês</div>
        </div>
        <div class="plan-card selected" data-plano="profissional" onclick="selecionarPlano(this)">
            <div class="plan-name">Profissional</div>
            <div class="plan-price"><sup>R$</sup>99<small style="font-size:14px">,90</small></div>
            <div class="plan-period">por mês</div>
            <div class="plan-badge">Popular</div>
        </div>
        <div class="plan-card" data-plano="enterprise" onclick="selecionarPlano(this)">
            <div class="plan-name">Enterprise</div>
            <div class="plan-price"><sup>R$</sup>249<small style="font-size:14px">,90</small></div>
            <div class="plan-period">por mês</div>
        </div>
    </div>
</div>

<form id="mainForm" method="POST" action="">
    <input type="hidden" name="plano" id="planoInput" value="profissional">
    <input type="hidden" name="cardToken" id="cardToken">
    <input type="hidden" name="paymentMethodId" id="paymentMethodId">
    <input type="hidden" name="installments" id="installments" value="1">
    <input type="hidden" name="issuerId" id="issuerId">

    <!-- DADOS DO CLIENTE -->
    <div class="card">
        <div class="section-label">Dados do cliente</div>

        <div class="field-row">
            <div class="field">
                <label for="nome">Nome completo</label>
                <input type="text" id="nome" name="nome" placeholder="João Silva" required
                       value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="joao@empresa.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="cpf">CPF</label>
                <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" required
                       value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="telefone">Telefone / WhatsApp</label>
                <input type="text" id="telefone" name="telefone" placeholder="(11) 99999-9999" maxlength="15" required
                       value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- DADOS DO CARTÃO -->
    <div class="card">
        <div class="section-label">Cartão de crédito</div>

        <div class="card-icons" id="cardIcons">
            <div class="card-icon visa">VISA</div>
            <div class="card-icon master">MC</div>
            <div class="card-icon elo">ELO</div>
            <div class="card-icon" style="color:var(--muted)">AMEX</div>
            <div class="card-icon" style="color:var(--muted)">HIPER</div>
        </div>

        <div class="field">
            <label>Número do cartão</label>
            <div class="mp-field-wrapper" id="wrapper-number">
                <div id="cardNumberElement"></div>
            </div>
        </div>

        <div class="field">
            <label for="cardholderName">Nome no cartão</label>
            <input type="text" id="cardholderName" placeholder="COMO IMPRESSO NO CARTÃO" autocomplete="cc-name">
        </div>

        <div class="field-row three">
            <div class="field" style="grid-column: span 2;">
                <label>Validade</label>
                <div class="mp-field-wrapper" id="wrapper-expiry">
                    <div id="expirationDateElement"></div>
                </div>
            </div>
            <div class="field">
                <label>CVV</label>
                <div class="mp-field-wrapper" id="wrapper-cvv">
                    <div id="securityCodeElement"></div>
                </div>
            </div>
        </div>

        <div class="field">
            <label for="docNumber">CPF do titular do cartão</label>
            <input type="text" id="docNumber" placeholder="000.000.000-00" maxlength="14" autocomplete="off">
        </div>

        <div class="security-note">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:var(--accent)">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            Os dados do cartão são tokenizados pelo Mercado Pago e <strong>nunca passam pelo nosso servidor</strong>. Conexão criptografada SSL.
        </div>
    </div>

    <button type="submit" class="btn-submit" id="submitBtn">
        <div class="spinner"></div>
        <span class="btn-text">✓ Cadastrar e cobrar primeira mensalidade</span>
    </button>
</form>

<div class="footer">
    Pagamentos processados com segurança por
    <br>
    <div class="mp-logo">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#009ee3"><circle cx="12" cy="12" r="10"/></svg>
        Mercado Pago
    </div>
</div>

<script>
// ============================================================
// INICIALIZAR SDK MERCADO PAGO
// ============================================================
const mp = new MercadoPago('<?= MP_PUBLIC_KEY ?>', { locale: 'pt-BR' });
const cardForm = mp.cardForm({
    amount: '99.90', // será atualizado ao selecionar plano
    iframe: true,
    form: {
        id: 'mainForm',
        cardNumber:     { id: 'cardNumberElement',     placeholder: '0000 0000 0000 0000' },
        expirationDate: { id: 'expirationDateElement', placeholder: 'MM/AA' },
        securityCode:   { id: 'securityCodeElement',   placeholder: 'CVV' },
        cardholderName: { id: 'cardholderName',        placeholder: 'COMO IMPRESSO NO CARTÃO' },
        identificationNumber: { id: 'docNumber' },
        identificationType:   { id: 'docType' },
    },
    callbacks: {
        onFormMounted: error => { if (error) console.error('Form mount error:', error); },
        onSubmit: async event => {
            event.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;

            const { paymentMethodId, issuerId, cardholderEmail, amount,
                    numberOfInstallments, identificationNumber, identificationType } = cardForm.getCardFormData();

            // Obter token
            let token;
            try {
                const tokenResponse = await mp.createCardToken({
                    cardholderName: document.getElementById('cardholderName').value,
                    identificationType: 'CPF',
                    identificationNumber: document.getElementById('docNumber').value.replace(/\D/g,''),
                });
                token = tokenResponse.id;
            } catch (e) {
                alert('Erro ao processar cartão: ' + (e.message || 'Tente novamente'));
                btn.classList.remove('loading');
                btn.disabled = false;
                return;
            }

            // Preencher campos ocultos
            document.getElementById('cardToken').value       = token;
            document.getElementById('paymentMethodId').value = paymentMethodId;
            document.getElementById('issuerId').value        = issuerId || '';
            document.getElementById('installments').value    = numberOfInstallments || 1;

            // Validar campos do cliente antes de enviar
            const nome     = document.getElementById('nome').value.trim();
            const email    = document.getElementById('email').value.trim();
            const cpf      = document.getElementById('cpf').value.replace(/\D/,'');
            const telefone = document.getElementById('telefone').value.replace(/\D/,'');

            if (!nome || !email || cpf.length < 11 || telefone.length < 10) {
                alert('Preencha todos os dados do cliente corretamente.');
                btn.classList.remove('loading');
                btn.disabled = false;
                return;
            }

            document.getElementById('mainForm').submit();
        },
        onFetching: resource => {
            // exibir loader se quiser por recurso
        },
        onPaymentMethodsReceived: (error, paymentMethods) => {
            if (!error && paymentMethods.length > 0) {
                // Destacar bandeira detectada
                const pm = paymentMethods[0].id; // ex: "visa", "master"
                document.querySelectorAll('.card-icon').forEach(el => el.classList.remove('active'));
                const iconMap = { visa: 0, master: 1, debmaster: 1, elo: 2, amex: 3, hipercard: 4 };
                const idx = iconMap[pm];
                if (idx !== undefined) {
                    document.querySelectorAll('.card-icon')[idx]?.classList.add('active');
                }
            }
        }
    }
});

// Focus visual nos wrappers MP iframe
['wrapper-number','wrapper-expiry','wrapper-cvv'].forEach(id => {
    const el = document.getElementById(id);
    // O SDK emite eventos nos iframes internos; podemos usar MutationObserver ou delegar
    el.addEventListener('focusin', () => el.classList.add('focused'));
    el.addEventListener('focusout', () => el.classList.remove('focused'));
});

// ============================================================
// SELEÇÃO DE PLANOS
// ============================================================
const valoresPlano = { basico: '49.90', profissional: '99.90', enterprise: '249.90' };

function selecionarPlano(card) {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    const plano = card.dataset.plano;
    document.getElementById('planoInput').value = plano;
    // Atualizar amount no cardForm (para cálculo de parcelas)
    cardForm.update({ amount: valoresPlano[plano] });
}

// ============================================================
// MÁSCARAS
// ============================================================
document.getElementById('cpf').addEventListener('input', function () {
    let v = this.value.replace(/\D/g,'').substring(0,11);
    v = v.replace(/(\d{3})(\d)/, '$1.$2')
         .replace(/(\d{3})(\d)/, '$1.$2')
         .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    this.value = v;
});

document.getElementById('telefone').addEventListener('input', function () {
    let v = this.value.replace(/\D/g,'').substring(0,11);
    if (v.length <= 10) {
        v = v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
    } else {
        v = v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
    }
    this.value = v;
});

document.getElementById('docNumber').addEventListener('input', function () {
    let v = this.value.replace(/\D/g,'').substring(0,11);
    v = v.replace(/(\d{3})(\d)/, '$1.$2')
         .replace(/(\d{3})(\d)/, '$1.$2')
         .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    this.value = v;
});
</script>
</body>
</html>