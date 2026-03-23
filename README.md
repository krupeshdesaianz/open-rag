# open-rag

Minimal RAG engine powered by Sarvam-105B and Pinecone. Upload documents, embed them into a vector store, and chat with the knowledge base — no login required.

Built with Laravel 11, OpenAI embeddings, Pinecone vector DB, and the Sarvam-105B LLM.

---

## How it works

```
┌─────────────────────────────────────────────────────────────┐
│  Upload & Train  (/)                                        │
│                                                             │
│  PDF/DOCX/TXT/MD ──► Text Extraction ──► Chunking          │
│                       (500 words, 50 overlap)               │
│                              │                              │
│                              ▼                              │
│                   OpenAI text-embedding-3-small             │
│                              │                              │
│                              ▼                              │
│                   Pinecone (namespace: open-rag)            │
└─────────────────────────────────────────────────────────────┘
                              │
                    status polling (3s)
                              │
┌─────────────────────────────────────────────────────────────┐
│  Chat  (/chat)                                              │
│                                                             │
│  User question                                              │
│       │                                                     │
│       ├──► Embed query (text-embedding-3-small)             │
│       ├──► Pinecone top-5 similarity search                 │
│       ├──► Build context + system guidelines                │
│       └──► Sarvam-105B ──► response                        │
└─────────────────────────────────────────────────────────────┘
```

**Queue:** ingestion runs as a background job (Laravel database queue). The UI polls `/status` every 3 seconds until the status changes to `ready` or `failed`.

---

## Requirements

- PHP 8.4+ (or Docker — recommended)
- Composer
- SQLite (default) or PostgreSQL
- API keys: OpenAI, Sarvam AI, Pinecone

---

## Quick Start

### With Docker (recommended)

```bash
git clone https://github.com/your-org/open-rag.git
cd open-rag

cp laravel/.env.example laravel/.env
# Edit laravel/.env — fill in the three API keys (see below)

docker compose up --build
```

App is available at **http://localhost:8000**

The container auto-runs migrations and starts the queue worker via Supervisor.

### Without Docker

```bash
git clone https://github.com/your-org/open-rag.git
cd open-rag/laravel

cp .env.example .env
# Edit .env — fill in the three API keys

composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Terminal 1 — web server
php artisan serve

# Terminal 2 — queue worker (required for ingestion)
php artisan queue:work database --tries=1 --timeout=600
```

---

## API Keys

You need three API keys. All are available on free tiers.

| Key | Purpose | Where to get it |
|-----|---------|----------------|
| `OPENAI_API_KEY` | Generates embeddings (`text-embedding-3-small`) | [platform.openai.com/api-keys](https://platform.openai.com/api-keys) |
| `SARVAM_API_KEY` | Chat completions (`sarvam-105b`) | [console.sarvam.ai](https://console.sarvam.ai) |
| `PINECONE_API_KEY` | Vector storage and similarity search | [app.pinecone.io](https://app.pinecone.io) |

### Pinecone setup

1. Create a free account at [pinecone.io](https://pinecone.io)
2. Create a new index:
   - **Dimensions:** `1536` (matches `text-embedding-3-small` output)
   - **Metric:** `cosine`
3. Copy the **Index Host URL** and **API Key** into `.env`

```env
PINECONE_API_KEY=your_key_here
PINECONE_HOST=https://your-index-xxxxxxx.svc.pinecone.io
PINECONE_INDEX=open-rag
```

---

## Usage

### 1. Upload documents

Open **http://localhost:8000** and drag-and-drop files into the upload zone.

- Supported formats: PDF, DOCX, TXT, MD
- Max file size: 15 MB per file
- Files are deleted from disk after ingestion; only vector embeddings are kept in Pinecone

### 2. Set system guidelines (optional)

The System Guidelines textarea lets you define instructions the AI always follows, for example:

```
You are a helpful assistant. Only answer questions using the provided knowledge base.
Do not speculate. If you don't know, say so.
```

Guidelines are saved to disk and injected into every chat request. They are never deleted from disk.

### 3. Train

Click **Train** to start ingestion. The page polls for status automatically. Once the banner shows "Knowledge base is ready", training is complete.

### 4. Chat

Click **Open Chat** (or go to `/chat`) to start querying. The AI answers only from your uploaded content.

---

## Configuration

All configuration is via `.env`. No database UI or admin panel.

| Variable | Description |
|----------|-------------|
| `APP_NAME` | Display name shown in the UI |
| `APP_DEBUG` | Set to `false` in production |
| `DB_CONNECTION` | `sqlite` (default) or `pgsql` |
| `OPENAI_API_KEY` | For embeddings |
| `SARVAM_API_KEY` | For chat completions |
| `PINECONE_API_KEY` | For vector storage |
| `PINECONE_HOST` | Your Pinecone index host URL |
| `PINECONE_INDEX` | Index name (default: `open-rag`) |

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 11 (PHP 8.4) |
| LLM | Sarvam-105B via `https://api.sarvam.ai/v1/chat/completions` |
| Embeddings | OpenAI `text-embedding-3-small` (1536-dim) |
| Vector DB | Pinecone (cosine similarity, top-5 retrieval) |
| Chunking | 500 words, 50-word overlap |
| Queue | Laravel database driver (no Redis required) |
| UI | Tailwind CSS (CDN), marked.js for markdown rendering |
| PDF parsing | `smalot/pdfparser` |
| DOCX parsing | `phpoffice/phpword` |

---

## Project Structure

```
open-rag/
├── docker-compose.yml
└── laravel/
    ├── app/
    │   ├── Http/Controllers/
    │   │   ├── UploadController.php   # upload, train, status, system guidelines
    │   │   └── ChatController.php     # chat page + query endpoint
    │   ├── Jobs/
    │   │   └── ProcessTwinIngestion.php
    │   ├── Models/
    │   │   ├── Twin.php               # singleton knowledge base record
    │   │   └── TwinFile.php
    │   └── Services/
    │       ├── IngestionService.php   # extract → chunk → embed → upsert
    │       └── AI/SarvamAIService.php
    ├── resources/views/
    │   ├── upload.blade.php           # Upload & Train page
    │   └── chat.blade.php             # Chat page
    ├── routes/web.php                 # 7 routes total
    ├── docker/
    │   ├── entrypoint.sh
    │   ├── supervisor/laravel.conf
    │   └── php/uploads.ini
    └── .env.example
```

---

## Contributing

1. Fork the repo
2. Create a branch: `git checkout -b feature/your-feature`
3. Make your changes
4. Open a pull request

Keep changes focused and the codebase minimal — the goal is a small, auditable RAG core.

---

## License

MIT
