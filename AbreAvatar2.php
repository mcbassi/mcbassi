<?php
// avatar_modal.php — adiciona carga de conhecimento D‑ID mantendo o restante intacto
// Requer: variável de ambiente DID_API_KEY (chave secreta da D‑ID para chamadas servidor-servidor)

// -----------------------------
// 1) Recepção do $textocompleto
// -----------------------------
$textocompleto = $textocompleto ?? ($_POST['textocompleto'] ?? $_GET['textocompleto'] ?? '');
$textoKB = trim((string)$textocompleto);
if ($textoKB !== '') {
    // Limita aos 10.000 primeiros caracteres
    $textoKB = mb_substr($textoKB, 0, 10000, 'UTF-8');
}

// ---------------------------------------------------------
// 2) Endpoint opcional interno para carregar conhecimento
//    (mesmo arquivo, via fetch POST ?action=load_kb)
// ---------------------------------------------------------
if (($_GET['action'] ?? '') === 'load_kb') {
    header('Content-Type: application/json; charset=utf-8');

    // Helpers HTTP simples para D‑ID
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true) ?: [];
    $agentId = (string)($in['agent_id'] ?? '');
    $text    = (string)($in['text'] ?? '');
    $kbName  = (string)($in['knowledge_name'] ?? ('KB Runtime - ' . date('c')));

    if ($agentId === '' || $text === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Parâmetros obrigatórios: agent_id e text']);
        exit;
    }

    $apiKey = getenv('DID_API_KEY'); // defina em seu ambiente de servidor
    if (!$apiKey) {
        http_response_code(500);
        echo json_encode(['error' => 'DID_API_KEY ausente no servidor.']);
        exit;
    }

    try {
        // 2.1) Cria Knowledge
        $kb = did_request('POST', '/knowledge', [
            'name'        => $kbName,
            'description' => 'KB dinâmica (' . date('c') . ')',
        ], $apiKey);
        $knowledgeId = $kb['id'] ?? null;
        if (!$knowledgeId) throw new RuntimeException('knowledgeId ausente.');

        // 2.2) Adiciona Document (inline)
        $doc = did_request('POST', "/knowledge/{$knowledgeId}/documents", [
            'documentType' => 'text',
            'title'        => mb_substr($kbName, 0, 120, 'UTF-8'),
            'content'      => $text,
        ], $apiKey);
        $docId = $doc['id'] ?? null;

        // 2.3) Polling até done (máx ~10s)
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $kbNow = did_request('GET', "/knowledge/{$knowledgeId}", null, $apiKey);
            if (($kbNow['status'] ?? null) === 'done') break;
            usleep(400000); // 0.4s
        }

        // 2.4) Vincula ao agente
        $upd = did_request('PATCH', "/agents/{$agentId}", [
            'knowledgeIds' => [$knowledgeId],
        ], $apiKey);

        echo json_encode([
            'ok' => true,
            'knowledge_id' => $knowledgeId,
            'document_id'  => $docId,
            'agent_updated'=> $upd,
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// -----------------------------
// Helper: requisições à API D‑ID
// -----------------------------
function did_request(string $method, string $path, ?array $body = null, ?string $apiKey = null): array {
    $base = 'https://api.d-id.com';
    $url  = rtrim($base, '/') . '/' . ltrim($path, '/');
    $key  = $apiKey ?: getenv('DID_API_KEY');
    if (!$key) throw new RuntimeException('DID_API_KEY ausente.');

    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($key . ':'),
    ];
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    if ($body !== null) {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resp   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno  = curl_errno($ch);
    curl_close($ch);

    if ($errno)   throw new RuntimeException('Falha cURL #' . $errno);
    $data = json_decode($resp, true);
    if ($status >= 400) {
        $msg = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string)$resp;
        throw new RuntimeException("Erro D‑ID {$status}: {$msg}");
    }
    return is_array($data) ? $data : [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Avatar com Prompt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Botão para abrir o modal -->
<div class="container mt-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#avatarModal">
        Abrir Avatar
    </button>
</div>

<!-- Modal com o Avatar e campos -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header">
        <h5 class="modal-title" id="avatarModalLabel">Assistente Virtual</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <!-- Avatar -->
        <div id="avatarContainer" style="width:100%;height:400px;">

        <script type="module"
              src="https://agent.d-id.com/v2/index.js"
              data-mode="fabio"
              data-client-key="YXV0aDB8Njg5YTBhNDU1OGIxMjExMGE4Y2MwYjUwOnlsdDI2TWFQR0pldXBNMUpzYkg3OA=="
              data-agent-id="v2_agt_7V7n2jDy"
              data-name="did-agent"
              data-monitor="true"
              data-orientation="horizontal"
              data-position="right">
        </script>

        
        </div>

        <!-- Campo de Prompt -->
        <div class="mt-4">
            <label for="promptInput" class="form-label">Digite o prompt:</label>
            <input type="text" id="promptInput" class="form-control" placeholder="Escreva sua pergunta...">
        </div>

        <!-- Botão de Enviar -->
        <button class="btn btn-success mt-2" onclick="enviarPrompt()">Enviar</button>

        <!-- Campo de Resposta -->
        <div class="mt-3">
            <label for="gptResponse" class="form-label">Resposta do GPT:</label>
            <textarea id="gptResponse" class="form-control" rows="4" readonly></textarea>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap e JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- 3) Função JS: carrega KB ANTES de iniciar fala/perguntas -->
<script>
// Texto vindo do servidor (já limitado a 10k chars em PHP)
window.DID_KB_TEXT = <?php echo json_encode($textoKB, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const DID_AGENT_ID  = 'v2_agt_7V7n2jDy'; // mesmo do embed acima
let __kbLoaded = false;

async function carregarConhecimentoSeNecessario(){
  if (__kbLoaded) return true;
  const text = (window.DID_KB_TEXT || '').trim();
  if (!text) { __kbLoaded = true; return true; } // nada a carregar

  try {
    const r = await fetch(location.pathname + '?action=load_kb', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ agent_id: DID_AGENT_ID, text, knowledge_name: 'KB Dinâmica - ' + new Date().toISOString() })
    });
    if (!r.ok) throw new Error((await r.text()) || ('HTTP ' + r.status));
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Falha ao anexar KB');
    __kbLoaded = true;
    console.log('KB anexada:', data);
    return true;
  } catch (e) {
    console.error('Erro ao carregar conhecimento D‑ID:', e);
    return false;
  }
}

// Exemplo: carregar KB ao abrir o modal (antes de interagir com o avatar)
const modalEl = document.getElementById('avatarModal');
modalEl?.addEventListener('show.bs.modal', () => { carregarConhecimentoSeNecessario(); });
</script>

<!-- JS Simulado para resposta GPT (mantido) -->
<script>
function enviarPrompt() {
    const prompt = document.getElementById("promptInput").value;
    const respostaBox = document.getElementById("gptResponse");

    // Garante KB carregada antes de "falar" / interagir (caso use integração real)
    carregarConhecimentoSeNecessario().then(() => {
      // Aqui você integraria com a API real do ChatGPT / D‑ID Agents SDK
      // Simulação de resposta:
      respostaBox.value = "Simulação de resposta para: " + prompt;
    });
}
</script>

</body>
</html>
