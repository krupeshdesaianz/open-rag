<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTwinIngestion;
use App\Models\Twin;
use App\Models\TwinFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    private function getTwin(): Twin
    {
        return Twin::firstOrCreate(
            ['id' => 1],
            [
                'uuid'   => (string) Str::uuid(),
                'name'   => config('app.name', 'open-rag'),
                'status' => 'pending',
            ]
        );
    }

    public function index()
    {
        $twin  = $this->getTwin();
        $files = $twin->files()->orderBy('created_at', 'desc')->get();

        return view('upload', compact('twin', 'files'));
    }

    public function upload(Request $request)
    {
        $twin = $this->getTwin();

        $request->validate([
            'file' => 'required|file|max:15360', // 15 MB
        ]);

        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed   = ['pdf', 'txt', 'docx', 'md'];

        if (!in_array($extension, $allowed)) {
            return response()->json([
                'error' => 'Invalid file type ".' . $extension . '". Allowed: PDF, DOCX, TXT, MD.',
            ], 422);
        }

        $warning = null;
        $path    = $file->store("rag/uploads", 'local');

        if (in_array($extension, ['txt', 'md'])) {
            $content = Storage::disk('local')->get($path);
            if (preg_match('/<(script|html|body|head|iframe|object|embed|form)\b/i', $content)) {
                $warning = 'Your file contains HTML tags. Content will be treated as plain text for training.';
            }
        }

        $twinFile = TwinFile::create([
            'twin_id'   => $twin->id,
            'filename'  => $file->getClientOriginalName(),
            'filepath'  => $path,
            'file_size' => $file->getSize(),
            'status'    => 'uploaded',
        ]);

        $response = ['success' => true, 'file' => $twinFile->toArray()];
        if ($warning) {
            $response['warning'] = $warning;
        }

        return response()->json($response);
    }

    public function deleteFile(TwinFile $file)
    {
        Storage::disk('local')->delete($file->filepath);
        $file->delete();

        return response()->json(['success' => true]);
    }

    public function train()
    {
        $twin = $this->getTwin();

        $uploadedCount = $twin->files()->where('status', 'uploaded')->count();

        if ($twin->files()->count() === 0) {
            return response()->json(['error' => 'No files uploaded yet.'], 422);
        }

        if ($uploadedCount === 0 && $twin->status === 'ready') {
            return response()->json(['error' => 'No new files to train. Upload files first.'], 422);
        }

        if ($twin->status === 'processing') {
            return response()->json(['error' => 'Training is already in progress.'], 422);
        }

        $twin->update(['status' => 'processing']);
        ProcessTwinIngestion::dispatch($twin);

        $totalSizeKb       = $twin->files()->where('status', 'uploaded')->sum('file_size') / 1024;
        $estimatedSeconds  = max(10, round($totalSizeKb / 20));
        $estimatedSeconds  = (int) (ceil($estimatedSeconds / 30) * 30);

        if ($estimatedSeconds < 60) {
            $estimatedTime = "~{$estimatedSeconds} seconds";
        } elseif ($estimatedSeconds < 3600) {
            $minutes       = ceil($estimatedSeconds / 60);
            $estimatedTime = "~{$minutes} minute" . ($minutes > 1 ? 's' : '');
        } else {
            $estimatedTime = "~" . ceil($estimatedSeconds / 3600) . " hours";
        }

        return response()->json(['success' => true, 'estimated_time' => $estimatedTime]);
    }

    public function status()
    {
        $twin = $this->getTwin();

        return response()->json([
            'status'         => $twin->status,
            'files_count'    => $twin->files()->count(),
            'ingested_count' => $twin->files()->where('status', 'ingested')->count(),
            'failed_count'   => $twin->files()->where('status', 'failed')->count(),
        ]);
    }

    public function updateSystemFile(Request $request)
    {
        $twin = $this->getTwin();

        $request->validate([
            'content' => 'required|string|max:50000',
        ]);

        $systemFile = $twin->files()->where('is_system_file', true)->first();

        $content  = $request->input('content');
        $filename = 'system_guidelines.txt';

        if ($systemFile) {
            // Overwrite existing system file
            Storage::disk('local')->put($systemFile->filepath, $content);
            $systemFile->update([
                'file_size' => strlen($content),
                'status'    => 'ingested',
            ]);
        } else {
            $path = "rag/system/{$filename}";
            Storage::disk('local')->put($path, $content);

            TwinFile::create([
                'twin_id'        => $twin->id,
                'filename'       => $filename,
                'filepath'       => $path,
                'file_size'      => strlen($content),
                'status'         => 'ingested',
                'is_system_file' => true,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function getSystemFile()
    {
        $twin       = $this->getTwin();
        $systemFile = $twin->files()->where('is_system_file', true)->first();

        if (!$systemFile || !Storage::disk('local')->exists($systemFile->filepath)) {
            return response()->json(['content' => '']);
        }

        return response()->json([
            'content' => Storage::disk('local')->get($systemFile->filepath),
        ]);
    }
}
