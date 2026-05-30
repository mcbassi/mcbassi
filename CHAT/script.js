function toggleChat() {
    const win = document.getElementById('chat-window');
    if (!win) return;

    const isOpen = win.classList.contains('active');
    win.classList.toggle('active', !isOpen);
    win.style.display = isOpen ? 'none' : 'flex';
}

async function sendMessage() {
    const input = document.getElementById('user-input');
    if (!input) return;

    const text = input.value.trim();
    if (!text) return;

    addMessage(text, 'user');
    input.value = '';

    const processUrl = window.CHAT_PROCESS_URL || '/CHAT/process.php';

    try {
        const res = await fetch(processUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: text })
        });

        const raw = await res.text();

        let data = {};
        try {
            data = JSON.parse(raw);
        } catch {
            throw new Error('Resposta inválida do servidor: ' + raw.slice(0, 200));
        }

        if (!res.ok) {
            throw new Error(
                data.answer ||
                data.message ||
                data.error ||
                ('HTTP ' + res.status)
            );
        }

        const answer =
            data.answer ||
            data.message ||
            data.output ||
            'Não recebi resposta do assistente.';

        addMessage(answer, 'bot');
        speak(answer);
    } catch (err) {
        addMessage('Erro ao consultar o assistente: ' + (err.message || String(err)), 'bot');
    }
}

function startVoice() {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
        alert('Reconhecimento de voz não suportado neste navegador.');
        return;
    }

    const recognition = new SR();
    recognition.lang = 'pt-BR';

    const btn = document.getElementById('btn-voice');
    if (btn) btn.style.color = 'red';

    recognition.onresult = (event) => {
        const transcript = event.results?.[0]?.[0]?.transcript || '';
        const input = document.getElementById('user-input');
        if (input) input.value = transcript;
        sendMessage();
    };

    recognition.onerror = () => {
        if (btn) btn.style.color = 'inherit';
    };

    recognition.onend = () => {
        if (btn) btn.style.color = 'inherit';
    };

    recognition.start();
}

function speak(text) {
    if (!('speechSynthesis' in window)) return;
    const utterance = new SpeechSynthesisUtterance(String(text || ''));
    utterance.lang = 'pt-BR';
    window.speechSynthesis.speak(utterance);
}

function addMessage(text, side) {
    const box = document.getElementById('chat-messages');
    if (!box) return;

    const div = document.createElement('div');
    div.className = `msg ${side}`;
    div.innerText = String(text || '');
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
}