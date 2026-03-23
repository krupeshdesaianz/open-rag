<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'open-rag') }} — Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .prose h1,.prose h2,.prose h3 { margin-top:1em; margin-bottom:.5em; font-weight:600; }
        .prose p { margin-bottom:.75em; }
        .prose ul,.prose ol { margin-left:1.5em; margin-bottom:.75em; }
        .prose code { background:#f3f4f6; padding:.2em .4em; border-radius:.25em; font-size:.9em; }
        .prose pre { background:#f3f4f6; padding:.75em 1em; border-radius:.5em; overflow-x:auto; margin-bottom:.75em; }
    </style>
</head>
<body class="bg-gray-50">
<div class="min-h-screen flex flex-col">

    <!-- Header -->
    <header class="border-b border-gray-200 sticky top-0 z-10"
            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ config('app.name', 'open-rag') }}</h1>
                <p class="text-sm text-white mt-0.5" style="opacity:.85;">AI Knowledge Base Chat</p>
            </div>
            <a href="{{ route('upload.index') }}" class="text-white hover:text-gray-100 font-medium text-sm">
                &larr; Upload &amp; Train
            </a>
        </div>
    </header>

    <!-- Chat Messages -->
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 py-6">
        <div id="chat-messages" class="space-y-4 mb-28">
            <!-- Welcome -->
            <div class="flex items-start">
                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-purple-600 flex items-center justify-center text-white font-semibold text-xs">
                    AI
                </div>
                <div class="ml-3 bg-white rounded-lg shadow p-4 max-w-3xl">
                    <p class="text-gray-800">Hi! I'm trained on your knowledge base. Ask me anything!</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Input Bar (fixed) -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <form id="chat-form" class="flex gap-2">
                <input type="text" id="user-input"
                       placeholder="Ask a question..."
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 text-sm"
                       autofocus />
                <button type="submit" id="send-btn"
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium disabled:opacity-50 disabled:cursor-not-allowed transition text-sm">
                    Send
                </button>
            </form>
        </div>
    </div>

</div>

<script>
const csrfToken    = document.querySelector('meta[name="csrf-token"]').content;
const chatMessages = document.getElementById('chat-messages');
const chatForm     = document.getElementById('chat-form');
const userInput    = document.getElementById('user-input');
const sendBtn      = document.getElementById('send-btn');

chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const message = userInput.value.trim();
    if (!message) return;

    userInput.disabled = true;
    sendBtn.disabled   = true;
    sendBtn.textContent = 'Sending...';

    addMessage(message, 'user');
    userInput.value = '';

    const typingId = addTypingIndicator();

    try {
        const res  = await fetch('{{ route("chat.query") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ message }),
        });

        const data = await res.json();
        removeTypingIndicator(typingId);

        if (res.ok) {
            addMessage(data.response, 'ai');
        } else {
            addMessage(data.error || 'Sorry, something went wrong.', 'error');
        }
    } catch (err) {
        removeTypingIndicator(typingId);
        addMessage('Network error. Please try again.', 'error');
    } finally {
        userInput.disabled = false;
        sendBtn.disabled   = false;
        sendBtn.textContent = 'Send';
        userInput.focus();
    }
});

function addMessage(content, type) {
    const div = document.createElement('div');
    div.className = 'flex items-start';

    if (type === 'user') {
        div.classList.add('justify-end');
        div.innerHTML = `<div class="bg-purple-600 text-white rounded-lg shadow p-4 max-w-3xl text-sm"><p>${escapeHtml(content)}</p></div>`;
    } else if (type === 'ai') {
        div.innerHTML = `
            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-purple-600 flex items-center justify-center text-white font-semibold text-xs">AI</div>
            <div class="ml-3 bg-white rounded-lg shadow p-4 max-w-3xl prose prose-sm text-sm">${marked.parse(content)}</div>`;
    } else {
        div.innerHTML = `
            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-red-500 flex items-center justify-center text-white font-semibold text-xs">!</div>
            <div class="ml-3 bg-red-50 border border-red-200 rounded-lg p-4 max-w-3xl text-sm"><p class="text-red-800">${escapeHtml(content)}</p></div>`;
    }

    chatMessages.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth', block: 'end' });
}

function addTypingIndicator() {
    const id  = 'typing-' + Date.now();
    const div = document.createElement('div');
    div.id    = id;
    div.className = 'flex items-start';
    div.innerHTML = `
        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-purple-600 flex items-center justify-center text-white font-semibold text-xs">AI</div>
        <div class="ml-3 bg-white rounded-lg shadow p-4 max-w-3xl">
            <div class="flex gap-1">
                <div class="w-2 h-2 rounded-full animate-bounce" style="background:#667eea;animation-delay:0ms"></div>
                <div class="w-2 h-2 rounded-full animate-bounce" style="background:#667eea;animation-delay:150ms"></div>
                <div class="w-2 h-2 rounded-full animate-bounce" style="background:#667eea;animation-delay:300ms"></div>
            </div>
        </div>`;
    chatMessages.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth', block: 'end' });
    return id;
}

function removeTypingIndicator(id) {
    document.getElementById(id)?.remove();
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
</body>
</html>
