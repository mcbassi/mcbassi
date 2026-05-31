<?php
/**
 * app/views/Clientes/cadastro.php
 * Renderizado dentro do layout principal.
 *
 * Variaveis:
 *   $resultado  array|null
 *   $erro       string|null
 *   $documentos array<int,array<string,mixed>>
 */

$mpPublicKey = defined('MP_PUBLIC_KEY') ? MP_PUBLIC_KEY : \App\Infra\Env::get('MP_PUBLIC_KEY', 'TEST-sua-chave-aqui');
$documentos = is_array($documentos ?? null) ? $documentos : [];

$envMpType = strtoupper(trim((string) \App\Infra\Env::get('MP_IDENTIFICATION_TYPE', '')));
$postedPais = strtoupper(trim((string) ($_POST['pais_codigo'] ?? '')));
$postedTipo = strtoupper(trim((string) ($_POST['documento_tipo'] ?? '')));
$selectedDoc = null;

foreach ($documentos as $doc) {
    $docPais = strtoupper((string) ($doc['pais_codigo'] ?? ''));
    $docTipo = strtoupper((string) ($doc['documento_tipo'] ?? ''));
    $docMpType = strtoupper((string) ($doc['mp_identification_type'] ?? ''));

    if ($postedPais !== '' && $postedTipo !== '' && $docPais === $postedPais && $docTipo === $postedTipo) {
        $selectedDoc = $doc;
        break;
    }

    if ($selectedDoc === null && $envMpType !== '' && $docMpType === $envMpType) {
        $selectedDoc = $doc;
    }
}

if ($selectedDoc === null) {
    foreach ($documentos as $doc) {
        if ((int) ($doc['is_default'] ?? 0) === 1) {
            $selectedDoc = $doc;
            break;
        }
    }
}

$selectedDoc ??= $documentos[0] ?? [
    'pais_codigo' => 'BR',
    'pais_nome' => 'Brasil',
    'documento_tipo' => 'CPF',
    'documento_nome' => 'CPF',
    'mp_identification_type' => 'CPF',
    'placeholder' => '000.000.000-00',
    'max_length' => 14,
    'phone_prefix' => '+55',
    'currency_code' => 'BRL',
    'currency_symbol' => 'R$',
    'amount_multiplier' => 1,
    'is_default' => 1,
];

$selectedPais = (string) $selectedDoc['pais_codigo'];
$selectedTipo = (string) $selectedDoc['documento_tipo'];
$documentoJson = json_encode($documentos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$mpPlanAmount = static function (float $amount) use ($selectedDoc): string {
    return number_format($amount * (float) ($selectedDoc['amount_multiplier'] ?? 1), 2, '.', '');
};
?>

<?php if ($resultado): ?>
<div class="alert alert--success" style="margin-bottom:20px">
    Cliente <strong><?= h($resultado['cliente']) ?></strong> cadastrado com sucesso!
    &nbsp;|&nbsp; Plano: <strong><?= h($resultado['plano']) ?></strong>
    &nbsp;|&nbsp; <?= h((string) ($resultado['currency_symbol'] ?? 'R$')) ?> <?= number_format((float) $resultado['valor'], strtoupper((string) ($resultado['currency_code'] ?? 'BRL')) === 'COP' ? 0 : 2, ',', '.') ?> <?= h((string) ($resultado['currency_code'] ?? 'BRL')) ?>/mes
    &nbsp;|&nbsp; Pagamento: <strong><?= h(strtoupper($resultado['status'])) ?></strong>
</div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert--danger" style="margin-bottom:20px">
    <?= h($erro) ?>
</div>
<?php endif; ?>

<div id="cad-mp-status" class="alert alert--warning" style="display:none;margin-bottom:20px"></div>

<script>
window.CAD_MP_ERROR_MESSAGES = [];
window.CAD_MP_DESCRIBE_ERROR = function (error) {
    if (!error) return 'Erro desconhecido';
    if (typeof error === 'string') return error;
    if (error.message) return error.message;
    try {
        return JSON.stringify(error, Object.getOwnPropertyNames(error));
    } catch (jsonError) {
        return String(error);
    }
};
window.addEventListener('error', function (event) {
    const source = event.filename || '';
    if (!source.includes('mercadopago.com') && !source.includes('mercadolibre.com')) return;
    const status = document.getElementById('cad-mp-status');
    const message = window.CAD_MP_DESCRIBE_ERROR(event.error || event.message);
    window.CAD_MP_ERROR_MESSAGES.push(message);
    if (status) {
        status.style.display = 'block';
        status.className = 'alert alert--danger';
        status.textContent = 'Erro no SDK do Mercado Pago: ' + message;
    }
});
window.addEventListener('unhandledrejection', function (event) {
    const status = document.getElementById('cad-mp-status');
    const message = window.CAD_MP_DESCRIBE_ERROR(event.reason);
    window.CAD_MP_ERROR_MESSAGES.push(message);
    if (status) {
        status.style.display = 'block';
        status.className = 'alert alert--danger';
        status.textContent = 'Erro no SDK do Mercado Pago: ' + message;
    }
});
</script>
<script src="https://sdk.mercadopago.com/js/v2" onerror="window.CAD_MP_SDK_ERROR=true"></script>

<style>
.cad-wrap { max-width: 620px; }
.cad-plans { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:24px; }
.cad-plan { border:2px solid #ddd; border-radius:10px; padding:14px; text-align:center; cursor:pointer; transition:.15s; }
.cad-plan:hover { border-color:#6366f1; }
.cad-plan.selected { border-color:#6366f1; background:#eef2ff; }
.cad-plan__name { font-size:11px; font-weight:600; text-transform:uppercase; color:#666; letter-spacing:.4px; }
.cad-plan__price { font-size:22px; font-weight:700; margin:4px 0; }
.cad-plan__period { font-size:11px; color:#777; }
.cad-plan__badge { display:inline-block; margin-top:6px; padding:2px 8px; background:#6366f1; color:#fff; border-radius:20px; font-size:9px; font-weight:700; }
.cad-section { margin-bottom:24px; }
.cad-section__title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#6366f1; margin-bottom:14px; padding-bottom:6px; border-bottom:1px solid #e5e7eb; }
.cad-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.cad-row.three { grid-template-columns:2fr 1fr; }
.cad-field { margin-bottom:14px; }
.cad-field label { display:block; font-size:12px; font-weight:500; color:#555; margin-bottom:5px; }
.cad-field input,
.cad-field select { width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:9px 12px; font-size:14px; outline:none; transition:.15s; background:#fff; }
.cad-field input:focus,
.cad-field select:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.mp-iframe-wrap { border:1.5px solid #d1d5db; border-radius:8px; height:39px; display:flex; align-items:center; padding:0 12px; transition:.15s; background:#fff; overflow:hidden; }
.mp-iframe-wrap.focused { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.mp-iframe-wrap > div { width:100%; height:100%; }
.cad-card-line { display:flex; align-items:flex-end; gap:10px; }
.cad-card-line .cad-field { flex:1; }
.cad-brand { min-width:94px; margin-bottom:14px; border:1px solid #d1d5db; border-radius:8px; min-height:42px; display:flex; align-items:center; justify-content:center; padding:6px 10px; color:#555; font-size:12px; font-weight:700; background:#f9fafb; }
.cad-brand img { max-height:24px; max-width:66px; display:block; }
.cad-check { display:flex; align-items:center; gap:8px; margin:2px 0 14px; font-size:13px; color:#444; }
.cad-check input { width:auto; }
.cad-cardholder-extra { display:none; }
.cad-cardholder-extra.is-open { display:block; }
.cad-security { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 14px; font-size:12px; color:#166534; margin-top:4px; }
.cad-btn { width:100%; padding:14px; background:#6366f1; color:#fff; font-weight:700; font-size:15px; border:none; border-radius:10px; cursor:pointer; transition:.15s; display:flex; align-items:center; justify-content:center; gap:8px; }
.cad-btn:hover { background:#4f46e5; transform:translateY(-1px); }
.cad-btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }
.cad-spinner { width:16px; height:16px; border:2px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; display:none; animation:spin .7s linear infinite; }
.cad-btn.loading .cad-spinner { display:block; }
@keyframes spin { to { transform:rotate(360deg); } }
@media (max-width: 720px) {
    .cad-plans, .cad-row { grid-template-columns:1fr; }
    .cad-card-line { align-items:stretch; flex-direction:column; gap:0; }
    .cad-brand { width:100%; }
}
</style>

<div class="cad-wrap">
    <div class="cad-section">
        <div class="cad-section__title">Plano</div>
        <div class="cad-plans" id="cad-plans">
            <div class="cad-plan" data-plano="basico" data-valor="49.90" data-valor-mp="<?= h($mpPlanAmount(49.90)) ?>" onclick="cadSelecionarPlano(this)">
                <div class="cad-plan__name">Basico</div>
                <div class="cad-plan__price">R$ 49<small style="font-size:13px">,90</small></div>
                <div class="cad-plan__period">por mes</div>
            </div>
            <div class="cad-plan selected" data-plano="profissional" data-valor="99.90" data-valor-mp="<?= h($mpPlanAmount(99.90)) ?>" onclick="cadSelecionarPlano(this)">
                <div class="cad-plan__name">Profissional</div>
                <div class="cad-plan__price">R$ 99<small style="font-size:13px">,90</small></div>
                <div class="cad-plan__period">por mes</div>
                <div class="cad-plan__badge">Popular</div>
            </div>
            <div class="cad-plan" data-plano="enterprise" data-valor="249.90" data-valor-mp="<?= h($mpPlanAmount(249.90)) ?>" onclick="cadSelecionarPlano(this)">
                <div class="cad-plan__name">Enterprise</div>
                <div class="cad-plan__price">R$ 249<small style="font-size:13px">,90</small></div>
                <div class="cad-plan__period">por mes</div>
            </div>
        </div>
    </div>

    <form id="cad-form" method="POST" action="<?= h(url('clientes/cadastro.php')) ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="plano" id="cad-plano" value="profissional">
        <input type="hidden" name="cardToken" id="cad-token">
        <input type="hidden" name="paymentMethodId" id="cad-pmid">
        <input type="hidden" name="installments" id="cad-inst" value="1">
        <input type="hidden" name="issuerId" id="cad-issuer">
        <input type="hidden" name="pais_codigo" id="cad-country-value" value="<?= h($selectedPais) ?>">
        <input type="hidden" name="documento_tipo" id="cad-document-type-value" value="<?= h($selectedTipo) ?>">

        <div class="cad-section">
            <div class="cad-section__title">Dados do cliente</div>
            <div class="cad-row">
                <div class="cad-field">
                    <label>Nome completo</label>
                    <input type="text" name="nome" placeholder="Joao Silva" required value="<?= h($_POST['nome'] ?? '') ?>">
                </div>
                <div class="cad-field">
                    <label>E-mail</label>
                    <input type="email" id="cad-email" name="email" placeholder="joao@empresa.com" required value="<?= h($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="cad-row">
                <div class="cad-field">
                    <label>Pais</label>
                    <select id="cad-country" required></select>
                </div>
                <div class="cad-field">
                    <label>Tipo de documento</label>
                    <select id="cad-document-type" required></select>
                </div>
            </div>
            <div class="cad-row">
                <div class="cad-field">
                    <label id="cad-document-label">Numero do documento</label>
                    <input type="text" id="cad-document-number" name="documento_numero" required value="<?= h($_POST['documento_numero'] ?? ($_POST['cpf'] ?? '')) ?>">
                </div>
                <div class="cad-field">
                    <label>Telefone / WhatsApp</label>
                    <input type="text" id="cad-tel" name="telefone" placeholder="+55 (11) 99999-9999" maxlength="22" required value="<?= h($_POST['telefone'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="cad-section">
            <div class="cad-section__title">Cartao de credito</div>

            <div class="cad-card-line">
                <div class="cad-field">
                    <label>Numero do cartao</label>
                    <div class="mp-iframe-wrap" id="cad-wrap-number">
                        <div id="cad-cardNumber"></div>
                    </div>
                </div>
                <div class="cad-brand" id="cad-card-brand">Bandeira</div>
            </div>

            <div class="cad-field">
                <label>Nome no cartao</label>
                <input type="text" id="cad-cardHolder" placeholder="COMO IMPRESSO NO CARTAO" style="text-transform:uppercase" autocomplete="cc-name">
            </div>

            <label class="cad-check">
                <input type="checkbox" id="cad-cardholder-same" name="cardholder_same" value="1" checked>
                Titular do cartao e o mesmo comprador
            </label>

            <div class="cad-cardholder-extra" id="cad-cardholder-extra">
                <div class="cad-row">
                    <div class="cad-field">
                        <label>Documento do titular</label>
                        <select id="cad-cardholder-document-type" name="cardholder_documento_tipo"></select>
                    </div>
                    <div class="cad-field">
                        <label>Numero do documento do titular</label>
                        <input type="text" id="cad-cardholder-document-number" name="cardholder_documento_numero">
                    </div>
                </div>
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
                <label>Documento do titular do cartao</label>
                <select id="cad-docType" style="display:none" aria-hidden="true" tabindex="-1"></select>
                <input type="text" id="cad-docNumber" readonly autocomplete="off">
            </div>

            <select id="cad-issuer-select" style="display:none" aria-hidden="true" tabindex="-1"></select>
            <select id="cad-installments-select" style="display:none" aria-hidden="true" tabindex="-1"></select>

            <div class="cad-security">
                Os dados do cartao sao tokenizados pelo Mercado Pago e nunca passam pelo nosso servidor.
            </div>
        </div>

        <button type="submit" class="cad-btn" id="cad-submit">
            <div class="cad-spinner"></div>
            <span>Cadastrar e cobrar primeira mensalidade</span>
        </button>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('cad-form');
    if (!form || form.dataset.cadInitialized === '1') {
        return;
    }
    form.dataset.cadInitialized = '1';

    const documentos = <?= $documentoJson ?: '[]' ?>;
    const selectedPais = '<?= h($selectedPais) ?>';
    const selectedTipo = '<?= h($selectedTipo) ?>';
    const sdkStatus = document.getElementById('cad-mp-status');
    const countrySelect = document.getElementById('cad-country');
    const documentTypeSelect = document.getElementById('cad-document-type');
    const countryValue = document.getElementById('cad-country-value');
    const documentTypeValue = document.getElementById('cad-document-type-value');
    const documentNumber = document.getElementById('cad-document-number');
    const cardDocumentType = document.getElementById('cad-docType');
    const cardDocumentNumber = document.getElementById('cad-docNumber');
    const cardholderSame = document.getElementById('cad-cardholder-same');
    const cardholderExtra = document.getElementById('cad-cardholder-extra');
    const cardholderDocumentType = document.getElementById('cad-cardholder-document-type');
    const cardholderDocumentNumber = document.getElementById('cad-cardholder-document-number');
    const cardBrand = document.getElementById('cad-card-brand');
    const phoneInput = document.getElementById('cad-tel');

    const describeMpError = window.CAD_MP_DESCRIBE_ERROR || function (error) {
        return error && error.message ? error.message : String(error || 'Erro desconhecido');
    };

    function setSdkStatus(message, isError) {
        if (!sdkStatus) return;
        sdkStatus.style.display = message ? 'block' : 'none';
        sdkStatus.className = 'alert ' + (isError ? 'alert--danger' : 'alert--warning');
        sdkStatus.textContent = message || '';
    }

    function countryRows() {
        const map = new Map();
        documentos.forEach(doc => {
            if (!map.has(doc.pais_codigo)) {
                map.set(doc.pais_codigo, doc.pais_nome);
            }
        });
        return Array.from(map.entries()).map(([codigo, nome]) => ({ codigo, nome }));
    }

    function currentDoc() {
        return documentos.find(doc => doc.pais_codigo === countrySelect.value && doc.documento_tipo === documentTypeSelect.value) || documentos[0];
    }

    function applyDocumentMask(value, doc) {
        let raw = String(value || '').replace(/[^\dA-Za-z]/g, '').toUpperCase();
        if (doc.mp_identification_type === 'CPF') {
            raw = raw.replace(/\D/g, '').slice(0, 11);
            return raw.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        if (doc.mp_identification_type === 'CNPJ') {
            raw = raw.replace(/\D/g, '').slice(0, 14);
            return raw.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
        }
        return raw.slice(0, Number(doc.max_length || 20));
    }

    function onlyDocumentChars(value) {
        return String(value || '').replace(/[^\dA-Za-z]/g, '').toUpperCase();
    }

    function isValidCpf(value) {
        const cpf = onlyDocumentChars(value).replace(/\D/g, '');
        if (!/^\d{11}$/.test(cpf) || /^(\d)\1{10}$/.test(cpf)) return false;
        for (let t = 9; t < 11; t++) {
            let sum = 0;
            for (let i = 0; i < t; i++) sum += Number(cpf[i]) * ((t + 1) - i);
            const digit = ((10 * sum) % 11) % 10;
            if (Number(cpf[t]) !== digit) return false;
        }
        return true;
    }

    function isValidCnpj(value) {
        const cnpj = onlyDocumentChars(value).replace(/\D/g, '');
        if (!/^\d{14}$/.test(cnpj) || /^(\d)\1{13}$/.test(cnpj)) return false;
        const weightSets = [[5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]];
        for (let d = 12; d <= 13; d++) {
            const sum = weightSets[d - 12].reduce((total, weight, index) => total + Number(cnpj[index]) * weight, 0);
            const rest = sum % 11;
            const digit = rest < 2 ? 0 : 11 - rest;
            if (Number(cnpj[d]) !== digit) return false;
        }
        return true;
    }

    function documentError(doc, value) {
        const raw = onlyDocumentChars(value);
        switch (doc.mp_identification_type) {
            case 'CPF':
                return isValidCpf(raw) ? '' : 'CPF invalido.';
            case 'CNPJ':
                return isValidCnpj(raw) ? '' : 'CNPJ invalido.';
            case 'CC':
            case 'TI':
                return /^\d{5,12}$/.test(raw) ? '' : doc.documento_nome + ' deve ter de 5 a 12 digitos.';
            case 'NIT':
                return /^\d{8,15}$/.test(raw) ? '' : 'NIT deve ter de 8 a 15 digitos.';
            case 'CE':
                return /^[A-Z0-9]{5,12}$/.test(raw) ? '' : 'Cedula de Extranjeria deve ter de 5 a 12 caracteres.';
            case 'PAS':
                return /^[A-Z0-9]{5,20}$/.test(raw) ? '' : 'Passaporte deve ter de 5 a 20 caracteres.';
            default:
                return raw.length >= 4 ? '' : 'Documento deve ter ao menos 4 caracteres.';
        }
    }

    function updateDocumentValidity() {
        documentNumber.setCustomValidity(documentError(currentDoc(), documentNumber.value));
        if (!cardholderSame.checked) {
            cardholderDocumentNumber.setCustomValidity(documentError(currentCardholderDoc(), cardholderDocumentNumber.value));
        } else {
            cardholderDocumentNumber.setCustomValidity('');
        }
    }

    function syncCardDocument() {
        const doc = currentCardholderDoc();
        cardDocumentType.innerHTML = '';
        const option = document.createElement('option');
        option.value = doc.mp_identification_type;
        option.textContent = doc.mp_identification_type;
        option.selected = true;
        cardDocumentType.appendChild(option);
        cardDocumentNumber.value = cardholderSame.checked ? documentNumber.value : cardholderDocumentNumber.value;
    }

    function currentCardholderDoc() {
        if (cardholderSame.checked) {
            return currentDoc();
        }

        return documentos.find(doc => doc.pais_codigo === countrySelect.value && doc.documento_tipo === cardholderDocumentType.value) || currentDoc();
    }

    function fillCardholderDocumentTypes() {
        const currentValue = cardholderDocumentType.value || documentTypeSelect.value;
        cardholderDocumentType.innerHTML = '';
        documentos.filter(doc => doc.pais_codigo === countrySelect.value).forEach(doc => {
            const option = document.createElement('option');
            option.value = doc.documento_tipo;
            option.textContent = doc.documento_nome;
            option.selected = doc.documento_tipo === currentValue;
            cardholderDocumentType.appendChild(option);
        });
        if (!cardholderDocumentType.value) {
            cardholderDocumentType.value = documentTypeSelect.value;
        }
        applyCardholderDocumentConfig();
    }

    function applyCardholderDocumentConfig() {
        const doc = currentCardholderDoc();
        cardholderDocumentNumber.placeholder = doc.placeholder || 'Numero do documento';
        cardholderDocumentNumber.maxLength = Number(doc.max_length || 20);
        cardholderDocumentNumber.value = applyDocumentMask(cardholderDocumentNumber.value, doc);
        updateDocumentValidity();
        syncCardDocument();
    }

    function syncCardholderMode() {
        const same = cardholderSame.checked;
        cardholderExtra.classList.toggle('is-open', !same);
        cardholderDocumentType.disabled = same;
        cardholderDocumentNumber.disabled = same;
        if (same) {
            cardholderDocumentType.value = documentTypeSelect.value;
            cardholderDocumentNumber.value = documentNumber.value;
        }
        updateDocumentValidity();
        syncCardDocument();
    }

    function applyDocumentConfig() {
        const doc = currentDoc();
        countryValue.value = countrySelect.value;
        documentTypeValue.value = documentTypeSelect.value;
        applyPhonePrefix(doc);
        document.getElementById('cad-document-label').textContent = doc.documento_nome + ' do cliente';
        documentNumber.placeholder = doc.placeholder || 'Numero do documento';
        documentNumber.maxLength = Number(doc.max_length || 20);
        documentNumber.value = applyDocumentMask(documentNumber.value, doc);
        document.querySelectorAll('.cad-plan').forEach(card => {
            card.dataset.valorMp = (Number(card.dataset.valor || 0) * Number(doc.amount_multiplier || 1)).toFixed(2);
        });
        fillCardholderDocumentTypes();
        syncCardDocument();
    }

    function phonePrefix(doc) {
        return String(doc.phone_prefix || '+55');
    }

    function nationalPhoneDigits(value, prefix) {
        let digits = String(value || '').replace(/\D/g, '');
        const prefixDigits = String(prefix || '').replace(/\D/g, '');
        if (prefixDigits && digits.startsWith(prefixDigits)) {
            digits = digits.slice(prefixDigits.length);
        }
        return digits.slice(0, 11);
    }

    function formatPhoneWithPrefix(value, doc) {
        const prefix = phonePrefix(doc);
        const national = nationalPhoneDigits(value, prefix);
        if (national === '') {
            return prefix + ' ';
        }
        if (national.length <= 2) {
            return prefix + ' (' + national;
        }
        if (national.length <= 6) {
            return prefix + ' (' + national.slice(0, 2) + ') ' + national.slice(2);
        }
        const local = national.slice(2);
        const split = local.length <= 8 ? 4 : 5;
        return prefix + ' (' + national.slice(0, 2) + ') ' + local.slice(0, split) + '-' + local.slice(split);
    }

    function applyPhonePrefix(doc) {
        const prefix = phonePrefix(doc);
        const current = phoneInput.value.trim();
        if (current === '') {
            phoneInput.value = prefix + ' ';
            return;
        }
        phoneInput.value = formatPhoneWithPrefix(current, doc);
    }

    countrySelect.innerHTML = '';
    documentTypeSelect.innerHTML = '';
    cardDocumentType.innerHTML = '';

    countryRows().forEach(country => {
        const option = document.createElement('option');
        option.value = country.codigo;
        option.textContent = country.nome;
        option.selected = country.codigo === selectedPais;
        countrySelect.appendChild(option);
    });

    function fillDocumentTypes(preferredType) {
        documentTypeSelect.innerHTML = '';
        const docs = documentos.filter(doc => doc.pais_codigo === countrySelect.value);
        docs.forEach(doc => {
            const option = document.createElement('option');
            option.value = doc.documento_tipo;
            option.textContent = doc.documento_nome;
            option.selected = doc.documento_tipo === preferredType || (!preferredType && Number(doc.is_default || 0) === 1);
            documentTypeSelect.appendChild(option);
        });
        if (!documentTypeSelect.value && docs[0]) {
            documentTypeSelect.value = docs[0].documento_tipo;
        }
        applyDocumentConfig();
    }

    countrySelect.addEventListener('change', () => fillDocumentTypes(''));
    documentTypeSelect.addEventListener('change', function () {
        applyDocumentConfig();
        if (cardholderSame.checked) {
            cardholderDocumentType.value = documentTypeSelect.value;
            cardholderDocumentNumber.value = documentNumber.value;
        }
        updateDocumentValidity();
        syncCardDocument();
    });
    documentNumber.addEventListener('input', function () {
        this.value = applyDocumentMask(this.value, currentDoc());
        if (cardholderSame.checked) {
            cardholderDocumentNumber.value = this.value;
        }
        updateDocumentValidity();
        syncCardDocument();
    });
    cardholderSame.addEventListener('change', syncCardholderMode);
    cardholderDocumentType.addEventListener('change', applyCardholderDocumentConfig);
    cardholderDocumentNumber.addEventListener('input', function () {
        this.value = applyDocumentMask(this.value, currentCardholderDoc());
        updateDocumentValidity();
        syncCardDocument();
    });

    fillDocumentTypes(selectedTipo);
    syncCardholderMode();

    phoneInput.addEventListener('focus', function () {
        if (this.value.trim() === '') {
            this.value = phonePrefix(currentDoc()) + ' ';
        }
    });
    phoneInput.addEventListener('input', function () {
        this.value = formatPhoneWithPrefix(this.value, currentDoc());
    });

    window.cadSelecionarPlano = function (card) {
        document.querySelectorAll('.cad-plan').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        document.getElementById('cad-plano').value = card.dataset.plano;
    };

    if (window.CAD_MP_SDK_ERROR || typeof MercadoPago === 'undefined') {
        setSdkStatus('SDK do Mercado Pago nao carregou. Verifique o acesso a https://sdk.mercadopago.com/js/v2.', true);
        return;
    }

    let cardForm;
    try {
        const mp = new MercadoPago('<?= h($mpPublicKey) ?>', { locale: 'pt-BR' });
        const selectedPlan = document.querySelector('.cad-plan.selected');
        const initialAmount = selectedPlan ? selectedPlan.dataset.valorMp : '<?= h($mpPlanAmount(99.90)) ?>';

        cardForm = mp.cardForm({
            amount: initialAmount,
            iframe: true,
            form: {
                id: form.id,
                cardNumber: { id: 'cad-cardNumber', placeholder: '0000 0000 0000 0000' },
                expirationDate: { id: 'cad-expiry', placeholder: 'MM/AA' },
                securityCode: { id: 'cad-cvv', placeholder: 'CVV' },
                cardholderName: { id: 'cad-cardHolder', placeholder: 'COMO IMPRESSO NO CARTAO' },
                cardholderEmail: { id: 'cad-email' },
                identificationNumber: { id: 'cad-docNumber' },
                identificationType: { id: 'cad-docType' },
                issuer: { id: 'cad-issuer-select' },
                installments: { id: 'cad-installments-select' },
            },
            callbacks: {
                onFormMounted: err => {
                    if (err) {
                        console.error('MP mount:', err);
                        setSdkStatus('Erro ao montar campos do Mercado Pago: ' + describeMpError(err), true);
                        return;
                    }
                    setSdkStatus('', false);
                },
                onPaymentMethodsReceived: (error, paymentMethods) => {
                    if (error) {
                        console.error('MP payment methods:', error);
                        setSdkStatus('Erro ao identificar meio de pagamento: ' + describeMpError(error), true);
                        return;
                    }
                    const method = Array.isArray(paymentMethods) ? paymentMethods[0] : paymentMethods;
                    if (method && method.thumbnail) {
                        cardBrand.innerHTML = '<img alt="' + (method.name || method.id || 'Bandeira') + '" src="' + method.thumbnail + '">';
                    } else if (method && (method.name || method.id)) {
                        cardBrand.textContent = method.name || method.id;
                    }
                },
                onInstallmentsReceived: error => {
                    if (error) {
                        console.error('MP installments:', error);
                        setSdkStatus('Nao foi possivel calcular parcelas. O pagamento sera tentado em 1 parcela.', true);
                        document.getElementById('cad-inst').value = 1;
                        return;
                    }
                    setSdkStatus('', false);
                },
                onSubmit: async event => {
                    event.preventDefault();
                    const btn = document.getElementById('cad-submit');
                    btn.classList.add('loading');
                    btn.disabled = true;

                    try {
                        countryValue.value = countrySelect.value;
                        documentTypeValue.value = documentTypeSelect.value;
                        syncCardDocument();
                        updateDocumentValidity();
                        if (!form.reportValidity()) {
                            throw new Error('Confira os campos obrigatorios e documentos informados.');
                        }
                        const formData = cardForm.getCardFormData();
                        document.getElementById('cad-token').value = formData.token || '';
                        document.getElementById('cad-pmid').value = formData.paymentMethodId || '';
                        document.getElementById('cad-issuer').value = formData.issuerId || '';
                        document.getElementById('cad-inst').value = formData.installments || formData.numberOfInstallments || 1;
                        if (formData.paymentMethodId && cardBrand.textContent === 'Bandeira') {
                            cardBrand.textContent = String(formData.paymentMethodId).toUpperCase();
                        }
                        if (!document.getElementById('cad-token').value) {
                            throw new Error('Mercado Pago nao gerou token do cartao. Confira os dados informados.');
                        }
                        form.submit();
                    } catch (e) {
                        alert('Erro ao processar cartao: ' + (e.message || 'Tente novamente.'));
                        btn.classList.remove('loading');
                        btn.disabled = false;
                    }
                },
            },
        });
    } catch (error) {
        console.error('MP init:', error);
        setSdkStatus('Erro ao inicializar Mercado Pago: ' + describeMpError(error), true);
        return;
    }

    ['cad-wrap-number', 'cad-wrap-expiry', 'cad-wrap-cvv'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener('focusin', () => el.classList.add('focused'));
        el.addEventListener('focusout', () => el.classList.remove('focused'));
    });
})();
</script>
