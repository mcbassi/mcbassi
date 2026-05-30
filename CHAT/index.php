<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div id="chat-widget-button" onclick="toggleChat()">
    <i class="fa-solid fa-comment-dots"></i>
</div>

<div id="chat-window" class="hidden">
    <div id="chat-header">
        <span>Assistente Prof. Marco</span>
        <button onclick="toggleChat()">×</button>
    </div>
    <div id="chat-messages">
        <div class="msg bot">Olá! Como posso ajudar com os documentos hoje?</div>
    </div>
    <div id="chat-input-area">
        <input type="text" id="user-input" placeholder="Digite ou fale...">
        <button id="btn-voice" onclick="startVoice()"><i class="fa-solid fa-microphone"></i></button>
        <button id="btn-send" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
    </div>
</div>

<script src="script.js"></script>

</body>
</html>