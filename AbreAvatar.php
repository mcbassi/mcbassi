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

<!-- JS Simulado para resposta GPT -->
<script>
function enviarPrompt() {
    const prompt = document.getElementById("promptInput").value;
    const respostaBox = document.getElementById("gptResponse");

    // Aqui você integraria com a API real do ChatGPT
    // Simulação de resposta:
    respostaBox.value = "Simulação de resposta para: " + prompt;
}
</script>

</body>
</html>
