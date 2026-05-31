<?php
$reqUri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
$projectBase = '';

if (preg_match('#^(/[^/]+)(?:/public)?(?:/|$)#i', $reqUri, $m)) {
    $projectBase = $m[1];
}

$chatCssUrl = $projectBase . '/CHAT/style.css';
$chatJsUrl = $projectBase . '/CHAT/script.js';
$chatProcessUrl = $projectBase . '/CHAT/process.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars($chatCssUrl, ENT_QUOTES, 'UTF-8') ?>">

<script>
window.CHAT_PROCESS_URL = <?= json_encode($chatProcessUrl, JSON_UNESCAPED_SLASHES) ?>;
</script>

<div id="chat-widget-button" onclick="toggleChat()">
    <span aria-hidden="true">Chat</span>
</div>

<div id="chat-window" class="hidden" style="display:none;">
    <div id="chat-header">
        <span>Assistente Prof. Marco</span>
        <button type="button" onclick="toggleChat()">×</button>
    </div>
    <div id="chat-messages">
        <div class="msg bot">Olá! Como posso ajudar com os documentos hoje?</div>
    </div>
    <div id="chat-input-area">
        <input type="text" id="user-input" placeholder="Digite ou fale...">
        <button id="btn-voice" type="button" onclick="startVoice()">
            Voz
        </button>
        <button id="btn-send" type="button" onclick="sendMessage()">
            Enviar
        </button>
    </div>
</div>

<script src="<?= htmlspecialchars($chatJsUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
