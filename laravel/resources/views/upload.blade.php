<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'open-rag') }} — Upload & Train</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Header -->
    <header style="background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);">
        <div class="max-w-3xl mx-auto px-6 py-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ config('app.name', 'open-rag') }}</h1>
                <p class="text-white text-sm mt-1" style="opacity:0.85;">Upload &amp; Train Knowledge Base</p>
            </div>
            @if($twin->status === 'ready')
            <a href="{{ route('chat.index') }}"
               class="px-5 py-2 bg-white text-purple-700 font-semibold rounded-lg shadow hover:bg-purple-50 transition text-sm">
                Open Chat &rarr;
            </a>
            @endif
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-6 py-8 space-y-8">

        @if(session('error'))
        <div class="bg-red-50 border border-red-300 text-red-800 rounded-lg px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
        @endif

        <!-- Status Banner -->
        <div id="status-banner" class="rounded-lg px-4 py-3 text-sm font-medium
            @if($twin->status === 'ready') bg-green-50 border border-green-300 text-green-800
            @elseif($twin->status === 'processing') bg-yellow-50 border border-yellow-300 text-yellow-800
            @elseif($twin->status === 'failed') bg-red-50 border border-red-300 text-red-800
            @else bg-gray-50 border border-gray-300 text-gray-700 @endif">
            <span id="status-text">
                @if($twin->status === 'ready') Knowledge base is ready.
                @elseif($twin->status === 'processing') Training in progress... please wait.
                @elseif($twin->status === 'failed') Training failed. Check your files and try again.
                @else Upload files below and click Train to build the knowledge base.
                @endif
            </span>
        </div>

        <!-- File Upload Card -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Upload Documents</h2>
            <p class="text-sm text-gray-500 mb-4">Supported: PDF, DOCX, TXT, MD &mdash; max 15 MB per file.</p>

            <!-- Drop Zone -->
            <div id="drop-zone"
                 class="border-2 border-dashed border-purple-300 rounded-xl p-8 text-center cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition"
                 onclick="document.getElementById('file-input').click()">
                <svg class="mx-auto mb-3 h-10 w-10 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-purple-600 font-medium">Click to upload or drag &amp; drop</p>
                <p class="text-gray-400 text-sm mt-1">PDF, DOCX, TXT, MD</p>
            </div>
            <input type="file" id="file-input" multiple accept=".pdf,.docx,.txt,.md" class="hidden">

            <!-- Upload Progress -->
            <div id="upload-progress" class="hidden mt-4">
                <div class="flex items-center gap-3 text-sm text-gray-600">
                    <svg class="animate-spin h-4 w-4 text-purple-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span id="upload-progress-text">Uploading...</span>
                </div>
            </div>

            <!-- Warning -->
            <div id="upload-warning" class="hidden mt-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"></div>

            <!-- Error -->
            <div id="upload-error" class="hidden mt-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
        </div>

        <!-- File List Card -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Uploaded Files</h2>

            <div id="file-list" class="space-y-2">
                @forelse($files->where('is_system_file', false) as $file)
                <div id="file-row-{{ $file->id }}" class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg class="flex-shrink-0 h-5 w-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $file->filename }}</p>
                            <p class="text-xs text-gray-400">{{ number_format($file->file_size / 1024, 1) }} KB
                                &mdash; <span class="
                                    @if($file->status === 'ingested') text-green-600
                                    @elseif($file->status === 'failed') text-red-600
                                    @elseif($file->status === 'processing') text-yellow-600
                                    @else text-gray-500 @endif">{{ ucfirst($file->status) }}</span>
                            </p>
                            @if($file->processing_notes)
                            <p class="text-xs text-red-500 mt-0.5">{{ $file->processing_notes }}</p>
                            @endif
                        </div>
                    </div>
                    <button onclick="deleteFile({{ $file->id }})"
                            class="flex-shrink-0 ml-3 text-gray-400 hover:text-red-500 transition"
                            title="Remove file">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                @empty
                <p id="empty-message" class="text-sm text-gray-400 text-center py-4">No files uploaded yet.</p>
                @endforelse
            </div>
        </div>

        <!-- System Guidelines Card -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-1">System Guidelines</h2>
            <p class="text-sm text-gray-500 mb-4">Instructions the AI always follows. This file is never deleted from disk.</p>

            <textarea id="system-guidelines"
                      rows="6"
                      placeholder="e.g. You are a helpful assistant. Always respond formally. Do not discuss topics outside the knowledge base."
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 resize-y font-mono"></textarea>

            <div class="flex items-center gap-3 mt-3">
                <button onclick="saveSystemFile()"
                        class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition">
                    Save Guidelines
                </button>
                <span id="guidelines-saved" class="hidden text-sm text-green-600">Saved!</span>
                <span id="guidelines-error" class="hidden text-sm text-red-600"></span>
            </div>
        </div>

        <!-- Train Button Card -->
        <div class="bg-white rounded-2xl shadow p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Train Knowledge Base</h2>
                <p class="text-sm text-gray-500 mt-1">Embeds all uploaded files into Pinecone vector storage.</p>
                <p id="estimated-time" class="text-sm text-purple-600 mt-1 hidden"></p>
            </div>
            <button id="train-btn"
                    onclick="startTraining()"
                    class="px-6 py-3 bg-purple-600 text-white font-semibold rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition text-sm whitespace-nowrap">
                {{ $twin->status === 'processing' ? 'Training...' : 'Train' }}
            </button>
        </div>

    </main>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// ── File upload ──────────────────────────────────────────────────────────────

const dropZone   = document.getElementById('drop-zone');
const fileInput  = document.getElementById('file-input');

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('border-purple-500','bg-purple-50'); });
dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('border-purple-500','bg-purple-50'); });
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('border-purple-500','bg-purple-50');
    uploadFiles(e.dataTransfer.files);
});
fileInput.addEventListener('change', () => uploadFiles(fileInput.files));

async function uploadFiles(files) {
    if (!files || files.length === 0) return;

    const progress = document.getElementById('upload-progress');
    const progressText = document.getElementById('upload-progress-text');
    const warning  = document.getElementById('upload-warning');
    const errorDiv = document.getElementById('upload-error');

    warning.classList.add('hidden');
    errorDiv.classList.add('hidden');

    for (const file of files) {
        progress.classList.remove('hidden');
        progressText.textContent = `Uploading ${file.name}...`;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', csrfToken);

        try {
            const res  = await fetch('/upload', { method: 'POST', body: formData });
            const data = await res.json();

            if (!res.ok) {
                errorDiv.textContent = data.error || 'Upload failed.';
                errorDiv.classList.remove('hidden');
            } else {
                if (data.warning) {
                    warning.textContent = data.warning;
                    warning.classList.remove('hidden');
                }
                addFileRow(data.file);
                document.getElementById('empty-message')?.remove();
            }
        } catch (e) {
            errorDiv.textContent = 'Network error during upload.';
            errorDiv.classList.remove('hidden');
        }
    }

    progress.classList.add('hidden');
    fileInput.value = '';
}

function addFileRow(file) {
    const list = document.getElementById('file-list');
    const row  = document.createElement('div');
    row.id = `file-row-${file.id}`;
    row.className = 'flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg';
    row.innerHTML = `
        <div class="flex items-center gap-3 min-w-0">
            <svg class="flex-shrink-0 h-5 w-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">${escapeHtml(file.filename)}</p>
                <p class="text-xs text-gray-400">${(file.file_size / 1024).toFixed(1)} KB &mdash; <span class="text-gray-500">Uploaded</span></p>
            </div>
        </div>
        <button onclick="deleteFile(${file.id})" class="flex-shrink-0 ml-3 text-gray-400 hover:text-red-500 transition" title="Remove file">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>`;
    list.appendChild(row);
}

async function deleteFile(id) {
    if (!confirm('Remove this file?')) return;

    const res = await fetch(`/files/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
    });

    if (res.ok) {
        document.getElementById(`file-row-${id}`)?.remove();
        const list = document.getElementById('file-list');
        if (list.children.length === 0) {
            list.innerHTML = '<p id="empty-message" class="text-sm text-gray-400 text-center py-4">No files uploaded yet.</p>';
        }
    }
}

// ── Training ─────────────────────────────────────────────────────────────────

let pollingInterval = null;

async function startTraining() {
    const btn = document.getElementById('train-btn');
    btn.disabled = true;
    btn.textContent = 'Starting...';

    const res  = await fetch('/train', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' }
    });
    const data = await res.json();

    if (!res.ok) {
        alert(data.error || 'Failed to start training.');
        btn.disabled = false;
        btn.textContent = 'Train';
        return;
    }

    btn.textContent = 'Training...';

    const etDiv = document.getElementById('estimated-time');
    etDiv.textContent = 'Estimated time: ' + data.estimated_time;
    etDiv.classList.remove('hidden');

    setStatusBanner('processing', 'Training in progress... please wait.');
    startPolling();
}

function startPolling() {
    if (pollingInterval) return;
    pollingInterval = setInterval(pollStatus, 3000);
}

async function pollStatus() {
    const res  = await fetch('/status', { headers: { 'Accept': 'application/json' } });
    const data = await res.json();

    const btn = document.getElementById('train-btn');

    if (data.status === 'ready') {
        clearInterval(pollingInterval);
        pollingInterval = null;
        setStatusBanner('ready', 'Knowledge base is ready!');
        btn.disabled = false;
        btn.textContent = 'Re-train';
        document.getElementById('estimated-time').classList.add('hidden');
    } else if (data.status === 'failed') {
        clearInterval(pollingInterval);
        pollingInterval = null;
        setStatusBanner('failed', 'Training failed. Check your files and try again.');
        btn.disabled = false;
        btn.textContent = 'Retry Train';
        document.getElementById('estimated-time').classList.add('hidden');
    }
}

function setStatusBanner(status, text) {
    const banner = document.getElementById('status-banner');
    const span   = document.getElementById('status-text');
    banner.className = 'rounded-lg px-4 py-3 text-sm font-medium ';
    if (status === 'ready')      banner.className += 'bg-green-50 border border-green-300 text-green-800';
    else if (status === 'processing') banner.className += 'bg-yellow-50 border border-yellow-300 text-yellow-800';
    else if (status === 'failed')     banner.className += 'bg-red-50 border border-red-300 text-red-800';
    else                              banner.className += 'bg-gray-50 border border-gray-300 text-gray-700';
    span.textContent = text;
}

// Auto-poll if currently processing
@if($twin->status === 'processing')
document.getElementById('train-btn').disabled = true;
startPolling();
@endif

// ── System Guidelines ────────────────────────────────────────────────────────

async function loadSystemFile() {
    const res  = await fetch('/system-file', { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    document.getElementById('system-guidelines').value = data.content || '';
}

async function saveSystemFile() {
    const content = document.getElementById('system-guidelines').value.trim();
    const saved   = document.getElementById('guidelines-saved');
    const err     = document.getElementById('guidelines-error');
    saved.classList.add('hidden');
    err.classList.add('hidden');

    const res  = await fetch('/system-file', {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ content })
    });
    const data = await res.json();

    if (res.ok) {
        saved.classList.remove('hidden');
        setTimeout(() => saved.classList.add('hidden'), 2000);
    } else {
        err.textContent = data.error || data.message || 'Save failed.';
        err.classList.remove('hidden');
    }
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

loadSystemFile();
</script>
</body>
</html>
