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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<script>
window.CHAT_PROCESS_URL = <?= json_encode($chatProcessUrl, JSON_UNESCAPED_SLASHES) ?>;
</script>

<div id="chat-widget-button" onclick="toggleChat()">
    <i class="fa-solid fa-comment-dots"></i>
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
            <i class="fa-solid fa-microphone"></i>
        </button>
        <button id="btn-send" type="button" onclick="sendMessage()">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>

<script src="<?= htmlspecialchars($chatJsUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
