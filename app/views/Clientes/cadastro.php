<?php
/**
 * app/views/clientes/cadastro.php
 * Renderizado dentro do layout app.php via View::render()
 * NÃO contém <html>, <head> nem <body>
 *
 * Variáveis disponíveis:
 *   $resultado  array|null   ['cliente','plano','valor','status']
 *   $erro       string|null
 */

// Chave pública MP — use variável de ambiente ou constante definida no .env
$mpPublicKey = defined('MP_PUBLIC_KEY') ? MP_PUBLIC_KEY
             : \App\Infra\Env::get('MP_PUBLIC_KEY', 'TEST-sua-chave-aqui');
?>

<?php if ($resultado): ?>
<div class="alert alert--success" style="margin-bottom:20px">
    ✅ Cliente <strong><?= h($resultado['cliente']) ?></strong> cadastrado com sucesso!
    &nbsp;|&nbsp; Plano: <strong><?= h($resultado['plano']) ?></strong>
    &nbsp;|&nbsp; R$ <?= number_format((float)$resultado['valor'], 2, ',', '.') ?>/mês
    &nbsp;|&nbsp; Pagamento: <strong><?= h(strtoupper($resultado['status'])) ?></strong>
</div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert--danger" style="margin-bottom:20px">
    ⚠️ <?= h($erro) ?>
</div>
<?php endif; ?>

<!-- SDK Mercado Pago (carregado aqui para não poluir o layout global) -->
<div id="cad-mp-status" class="alert alert--warning" style="display:none;margin-bottom:20px">
    Aguardando carregamento do Mercado Pago...
</div>

<script src="https://sdk.mercadopago.com/js/v2" onerror="window.CAD_MP_SDK_ERROR=true"></script>

<style>
/* ── Estilos escopados para esta tela ── */
.cad-wrap          { max-width: 620px; }
.cad-plans         { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:24px; }
.cad-plan          { border:2px solid #ddd; border-radius:10px; padding:14px; text-align:center; cursor:pointer; transition:.15s; }
.cad-plan:hover    { border-color:#6366f1; }
.cad-plan.selected { border-color:#6366f1; background:#eef2ff; }
.cad-plan__name    { font-size:11px; font-weight:600; text-transform:uppercase; color:#888; letter-spacing:.8px; }
.cad-plan__price   { font-size:22px; font-weight:700; margin:4px 0; }
.cad-plan__period  { font-size:11px; color:#aaa; }
.cad-plan__badge   { display:inline-block; margin-top:6px; padding:2px 8px; background:#6366f1; color:#fff; border-radius:20px; font-size:9px; font-weight:700; }
.cad-section       { margin-bottom:24px; }
.cad-section__title{ font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#6366f1; margin-bottom:14px; padding-bottom:6px; border-bottom:1px solid #e5e7eb; }
.cad-row           { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.cad-row.single    { grid-template-columns:1fr; }
.cad-row.three     { grid-template-columns:2fr 1fr; }
.cad-field         { margin-bottom:14px; }
.cad-field label   { display:block; font-size:12px; font-weight:500; color:#555; margin-bottom:5px; }
.cad-field input,
.cad-field select  { width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:9px 12px; font-size:14px; outline:none; transition:.15s; }
.cad-field input:focus,
.cad-field select:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.mp-iframe-wrap    { border:1.5px solid #d1d5db; border-radius:8px; height:42px; display:flex; align-items:center; padding:0 12px; transition:.15s; }
.mp-iframe-wrap.focused { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.mp-iframe-wrap > div  { width:100%; height:100%; }
.cad-security      { display:flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 14px; font-size:12px; color:#166534; margin-top:4px; }
.cad-btn           { width:100%; padding:14px; background:#6366f1; color:#fff; font-weight:700; font-size:15px; border:none; border-radius:10px; cursor:pointer; transition:.15s; display:flex; align-items:center; justify-content:center; gap:8px; }
.cad-btn:hover     { background:#4f46e5; transform:translateY(-1px); }
.cad-btn:disabled  { opacity:.6; cursor:not-allowed; transform:none; }
.cad-spinner       { width:16px; height:16px; border:2px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; display:none; animation:spin .7s linear infinite; }
.cad-btn.loading .cad-spinner { display:block; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>

<div class="cad-wrap">

    <!-- PLANOS -->
    <div class="cad-section">
        <div class="cad-section__title">Plano</div>
        <div class="cad-plans" id="cad-plans">
            <div class="cad-plan" data-plano="basico" data-valor="49.90" onclick="cadSelecionarPlano(this)">
                <div class="cad-plan__name">Básico</div>
                <div class="cad-plan__price">R$ 49<small style="font-size:13px">,90</small></div>
                <div class="cad-plan__period">por mês</div>
            </div>
            <div class="cad-plan selected" data-plano="profissional" data-valor="99.90" onclick="cadSelecionarPlano(this)">
                <div class="cad-plan__name">Profissional</div>
                <div class="cad-plan__price">R$ 99<small style="font-size:13px">,90</small></div>
                <div class="cad-plan__period">por mês</div>
                <div class="cad-plan__badge">Popular</div>
            </div>
            <div class="cad-plan" data-plano="enterprise" data-valor="249.90" onclick="cadSelecionarPlano(this)">
                <div class="cad-plan__name">Enterprise</div>
                <div class="cad-plan__price">R$ 249<small style="font-size:13px">,90</small></div>
                <div class="cad-plan__period">por mês</div>
            </div>
        </div>
    </div>

    <form id="cad-form" method="POST" action="<?= h(url('clientes/cadastro.php')) ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="plano"           id="cad-plano"   value="profissional">
        <input type="hidden" name="cardToken"        id="cad-token">
        <input type="hidden" name="paymentMethodId"  id="cad-pmid">
        <input type="hidden" name="installments"     id="cad-inst"    value="1">
        <input type="hidden" name="issuerId"         id="cad-issuer">

        <!-- DADOS DO CLIENTE -->
        <div class="cad-section">
            <div class="cad-section__title">Dados do cliente</div>
            <div class="cad-row">
                <div class="cad-field">
                    <label>Nome completo</label>
                    <input type="text" name="nome" placeholder="João Silva" required
                           value="<?= h($_POST['nome'] ?? '') ?>">
                </div>
                <div class="cad-field">
                    <label>E-mail</label>
                    <input type="email" name="email" placeholder="joao@empresa.com" required
                           value="<?= h($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="cad-row">
                <div class="cad-field">
                    <label>CPF</label>
                    <input type="text" id="cad-cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" required
                           value="<?= h($_POST['cpf'] ?? '') ?>">
                </div>
                <div class="cad-field">
                    <label>Telefone / WhatsApp</label>
                    <input type="text" id="cad-tel" name="telefone" placeholder="(11) 99999-9999" maxlength="15" required
                           value="<?= h($_POST['telefone'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- CARTÃO -->
        <div class="cad-section">
            <div class="cad-section__title">Cartão de crédito</div>

            <div class="cad-field">
                <label>Número do cartão</label>
                <div class="mp-iframe-wrap" id="cad-wrap-number">
                    <div id="cad-cardNumber"></div>
                </div>
            </div>

            <div class="cad-field">
                <label>Nome no cartão</label>
                <input type="text" id="cad-cardHolder" placeholder="COMO IMPRESSO NO CARTÃO"
                       style="text-transform:uppercase; letter-spacing:1px" autocomplete="cc-name">
            </div>

            <div class="cad-row three">
                <div class="cad-field">
                    <label>Validade</label>
                    <div class="mp-iframe-wrap" id="cad-wrap-expiry">
                        <div id="cad-expiry"></div>
                    </div>
                </div>
                <div class="cad-field">
                    <label>CVV</label>
                    <div class="mp-iframe-wrap" id="cad-wrap-cvv">
                        <div id="cad-cvv"></div>
                    </div>
                </div>
            </div>

            <div class="cad-field">
                <label>CPF do titular do cartão</label>
                <input type="text" id="cad-docNumber" placeholder="000.000.000-00" maxlength="14" autocomplete="off">
            </div>

            <div class="cad-security">
                🔒 Os dados do cartão são tokenizados pelo <strong>Mercado Pago</strong> e nunca passam pelo nosso servidor.
            </div>
        </div>

        <button type="submit" class="cad-btn" id="cad-submit">
            <div class="cad-spinner"></div>
            <span>✓ Cadastrar e cobrar primeira mensalidade</span>
        </button>
    </form>
</div>

<script>
(function () {
    const sdkStatus = document.getElementById('cad-mp-status');
    function setSdkStatus(message, isError) {
        if (!sdkStatus) return;
        sdkStatus.style.display = message ? 'block' : 'none';
        sdkStatus.className = 'alert ' + (isError ? 'alert--danger' : 'alert--warning');
        sdkStatus.textContent = message || '';
    }

    if (window.CAD_MP_SDK_ERROR || typeof MercadoPago === 'undefined') {
        setSdkStatus('SDK do Mercado Pago nao carregou. Verifique o acesso a https://sdk.mercadopago.com/js/v2 no navegador/Norton.', true);
        return;
    }
    // ── Inicializa SDK MP ─────────────────────────────────────────
    const mp = new MercadoPago('<?= h($mpPublicKey) ?>', { locale: 'pt-BR' });

    const cardForm = mp.cardForm({
        amount: '99.90',
        iframe: true,
        form: {
            id: 'cad-form',
            cardNumber:     { id: 'cad-cardNumber', placeholder: '0000 0000 0000 0000' },
            expirationDate: { id: 'cad-expiry',     placeholder: 'MM/AA' },
            securityCode:   { id: 'cad-cvv',        placeholder: 'CVV' },
            cardholderName: { id: 'cad-cardHolder', placeholder: 'COMO IMPRESSO NO CARTÃO' },
            identificationNumber: { id: 'cad-docNumber' },
            identificationType:   { id: 'cad-docType' },
        },
        callbacks: {
            onFormMounted: err => { if (err) console.error('MP mount:', err); },
            onSubmit: async event => {
                event.preventDefault();

                const btn = document.getElementById('cad-submit');
                btn.classList.add('loading');
                btn.disabled = true;

                try {
                    const tokenResp = await mp.createCardToken({
                        cardholderName:      document.getElementById('cad-cardHolder').value,
                        identificationType:  'CPF',
                        identificationNumber: document.getElementById('cad-docNumber').value.replace(/\D/g, ''),
                    });

                    const { paymentMethodId, issuerId, numberOfInstallments } = cardForm.getCardFormData();

                    document.getElementById('cad-token').value  = tokenResp.id;
                    document.getElementById('cad-pmid').value   = paymentMethodId  || '';
                    document.getElementById('cad-issuer').value = issuerId         || '';
                    document.getElementById('cad-inst').value   = numberOfInstallments || 1;

                    document.getElementById('cad-form').submit();
                } catch (e) {
                    alert('Erro ao processar cartão: ' + (e.message || 'Tente novamente.'));
                    btn.classList.remove('loading');
                    btn.disabled = false;
                }
            },
        },
    });

    // ── Seleção de plano ──────────────────────────────────────────
    window.cadSelecionarPlano = function (card) {
        document.querySelectorAll('.cad-plan').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        document.getElementById('cad-plano').value = card.dataset.plano;
        cardForm.update({ amount: card.dataset.valor });
    };

    // ── Máscaras ──────────────────────────────────────────────────
    document.getElementById('cad-cpf').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2')
             .replace(/(\d{3})(\d)/, '$1.$2')
             .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        this.value = v;
    });

    document.getElementById('cad-tel').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v.length <= 10
            ? v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2')
            : v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
        this.value = v;
    });

    document.getElementById('cad-docNumber').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2')
             .replace(/(\d{3})(\d)/, '$1.$2')
             .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        this.value = v;
    });

    // Focus visual nos wrappers dos iframes MP
    ['cad-wrap-number', 'cad-wrap-expiry', 'cad-wrap-cvv'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener('focusin',  () => el.classList.add('focused'));
        el.addEventListener('focusout', () => el.classList.remove('focused'));
    });
})();
</script>
