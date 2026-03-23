<?php

namespace App\Http\Controllers;

use App\Models\Twin;
use App\Services\AI\SarvamAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
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
        $twin = $this->getTwin();

        if ($twin->status !== 'ready') {
            return redirect('/')->with('error', 'Please upload files and train the knowledge base first.');
        }

        return view('chat', compact('twin'));
    }

    public function query(Request $request)
    {
        $twin = $this->getTwin();

        if ($twin->status !== 'ready') {
            return response()->json(['error' => 'Knowledge base is not ready. Please train first.'], 422);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $userMessage = $validated['message'];

        try {
            $embedding = $this->createEmbedding($userMessage);
            $context   = $this->searchPinecone($twin, $embedding);
            $response  = $this->generateResponse($twin, $userMessage, $context);

            return response()->json(['response' => $response]);

        } catch (\Exception $e) {
            Log::error("Query failed: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to process your question. Please try again.'], 500);
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

        return $response->json()['data'][0]['embedding'];
    }

    private function searchPinecone(Twin $twin, array $embedding): array
    {
        $pineconeHost   = config('services.pinecone.host');
        $pineconeApiKey = config('services.pinecone.api_key');

        $response = Http::withHeaders([
            'Api-Key'      => $pineconeApiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post("{$pineconeHost}/query", [
            'vector'          => $embedding,
            'topK'            => 5,
            'includeMetadata' => true,
            'namespace'       => config('services.pinecone.index', 'open-rag'),
        ]);

        if (!$response->successful()) {
            throw new \Exception("Pinecone search failed: {$response->status()}");
        }

        $matches = $response->json()['matches'] ?? [];

        $chunks = [];
        foreach ($matches as $match) {
            if (isset($match['metadata']['text'])) {
                $chunks[] = $match['metadata']['text'];
            }
        }

        return $chunks;
    }

    private function generateResponse(Twin $twin, string $query, array $context): string
    {
        $contextText = implode("\n\n", $context);

        $systemPrompt = "You are an AI assistant trained on specific content. Answer questions based ONLY on the provided context. If the answer is not in the context, say 'I don't have information about that in my knowledge base.'

Context from knowledge base:
{$contextText}";

        // Append system guidelines if they exist
        $systemFile = $twin->files()
            ->where('is_system_file', true)
            ->first();

        if ($systemFile && \Illuminate\Support\Facades\Storage::disk('local')->exists($systemFile->filepath)) {
            $guidelines    = \Illuminate\Support\Facades\Storage::disk('local')->get($systemFile->filepath);
            $systemPrompt .= "\n\nSYSTEM GUIDELINES (always follow these):\n" . $guidelines;
        }

        $sarvam = new SarvamAIService();
        return $sarvam->generateResponse($query, $systemPrompt);
    }
}
