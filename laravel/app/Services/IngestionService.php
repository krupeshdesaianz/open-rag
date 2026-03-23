<?php

namespace App\Services;

use App\Models\Twin;
use App\Models\TwinFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;

class IngestionService
{
    public function ingestFile(Twin $twin, TwinFile $file): void
    {
        Log::info("Starting ingestion for file: {$file->filename}");

        $content = $this->extractText($file);

        if (empty($content)) {
            Log::info("Skipping embedding for {$file->filename}: no content.");
            return;
        }

        $chunks = $this->chunkText($content);
        Log::info("Created " . count($chunks) . " chunks from {$file->filename}");

        $this->upsertToPinecone($file, $chunks);

        if (!$file->is_system_file) {
            Storage::disk('local')->delete($file->filepath);
            Log::info("Deleted file from disk after ingestion: {$file->filename}");
        }

        Log::info("Completed ingestion for file: {$file->filename}");
    }

    private function extractText(TwinFile $file): string
    {
        $filePath  = Storage::disk('local')->path($file->filepath);
        $extension = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));

        if (!file_exists($filePath)) {
            Log::warning("File not found on disk, skipping: {$file->filename}");
            $file->update(['status' => 'ingested', 'processing_notes' => 'File was previously processed.']);
            return '';
        }

        try {
            switch ($extension) {
                case 'pdf':
                    $text = $this->extractFromPdf($filePath);
                    if (preg_match('/<[^>]+>/', $text)) {
                        $text = strip_tags($text);
                    }
                    return $text;

                case 'docx':
                    return $this->extractFromDocx($filePath);

                case 'txt':
                case 'md':
                    $content = Storage::disk('local')->get($file->filepath);
                    if (preg_match('/<[^>]+>/', $content)) {
                        $content = strip_tags($content);
                    }
                    return $content;

                default:
                    throw new \Exception("Unsupported file type: {$extension}");
            }
        } catch (\Exception $e) {
            Log::error("Text extraction failed for {$file->filename}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function extractFromPdf(string $filePath): string
    {
        $parser = new PdfParser();
        $pdf    = $parser->parseFile($filePath);
        $text   = $pdf->getText();
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function extractFromDocx(string $filePath): string
    {
        $phpWord = IOFactory::load($filePath);
        $text    = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . ' ';
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if (method_exists($child, 'getText')) {
                            $text .= $child->getText() . ' ';
                        }
                    }
                }
            }
        }

        return trim($text);
    }

    private function chunkText(string $text): array
    {
        $words      = preg_split('/\s+/', $text);
        $totalWords = count($words);

        if ($totalWords === 0) {
            return [];
        }

        $chunks    = [];
        $chunkSize = 500;
        $overlap   = 50;
        $step      = $chunkSize - $overlap;

        for ($i = 0; $i < $totalWords; $i += $step) {
            $chunk = array_slice($words, $i, $chunkSize);
            if (count($chunk) > 0) {
                $chunks[] = implode(' ', $chunk);
            }
            if ($i + $chunkSize >= $totalWords) {
                break;
            }
        }

        return $chunks;
    }

    private function upsertToPinecone(TwinFile $file, array $chunks): void
    {
        $namespace      = config('services.pinecone.index', 'open-rag');
        $pineconeHost   = config('services.pinecone.host');
        $pineconeApiKey = config('services.pinecone.api_key');

        Log::info("Upserting " . count($chunks) . " chunks to Pinecone namespace: {$namespace}");

        foreach (array_chunk($chunks, 10) as $batchIndex => $batch) {
            $vectors = [];

            foreach ($batch as $chunkIndex => $chunk) {
                $globalIndex = ($batchIndex * 10) + $chunkIndex;
                $embedding   = $this->createEmbedding($chunk);

                $vectors[] = [
                    'id'       => "file_{$file->id}_chunk_{$globalIndex}",
                    'values'   => $embedding,
                    'metadata' => [
                        'text'     => $chunk,
                        'file_id'  => $file->id,
                        'filename' => $file->filename,
                    ],
                ];
            }

            $response = Http::withHeaders([
                'Api-Key'      => $pineconeApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$pineconeHost}/vectors/upsert", [
                'vectors'   => $vectors,
                'namespace' => $namespace,
            ]);

            if (!$response->successful()) {
                throw new \Exception("Pinecone upsert failed: {$response->status()} - {$response->body()}");
            }

            Log::info("Batch {$batchIndex} upserted successfully");
            usleep(100000); // 100ms between batches
        }
    }

    private function createEmbedding(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/embeddings', [
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        if (!$response->successful()) {
            throw new \Exception("OpenAI embedding failed: {$response->status()}");
        }

        $data = $response->json();

        if (!isset($data['data'][0]['embedding'])) {
            throw new \Exception("Invalid OpenAI response format");
        }

        return $data['data'][0]['embedding'];
    }
}
