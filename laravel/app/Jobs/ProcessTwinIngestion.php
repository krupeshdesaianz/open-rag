<?php

namespace App\Jobs;

use App\Models\Twin;
use App\Services\IngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTwinIngestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries   = 1;

    public function __construct(public Twin $twin)
    {
    }

    public function handle(IngestionService $service): void
    {
        Log::info("Starting ingestion job for twin: {$this->twin->id}");

        try {
            $this->twin->update(['status' => 'processing']);

            foreach ($this->twin->files()->where('status', 'uploaded')->get() as $file) {
                Log::info("Processing file: {$file->filename}");
                $file->update(['status' => 'processing']);

                try {
                    $service->ingestFile($this->twin, $file);
                    $file->update(['status' => 'ingested']);
                    Log::info("File completed: {$file->filename}");
                } catch (\Exception $e) {
                    $notes = $this->classifyError($e->getMessage());
                    $file->update(['status' => 'failed', 'processing_notes' => $notes]);
                    Log::error("File failed for {$file->filename}: {$e->getMessage()}");
                    throw $e;
                }
            }

            $this->twin->update(['status' => 'ready']);
            Log::info("Ingestion job completed successfully for twin: {$this->twin->id}");

        } catch (\Exception $e) {
            Log::error("Ingestion job failed for twin {$this->twin->id}: {$e->getMessage()}");
            $this->twin->update(['status' => 'failed']);
            throw $e;
        }
    }

    private function classifyError(string $message): string
    {
        if (stripos($message, 'Unsupported file type') !== false) {
            return 'Unsupported file type. Use PDF, DOCX, TXT or MD.';
        }
        if (stripos($message, 'password') !== false || stripos($message, 'encrypted') !== false) {
            return 'PDF is password protected. Remove the password and re-upload.';
        }
        if (stripos($message, 'corrupt') !== false || stripos($message, 'damaged') !== false) {
            return 'File appears corrupted. Try re-uploading.';
        }
        return 'Processing failed: ' . $message;
    }
}
