<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SarvamAIService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.sarvam.api_key');
    }

    public function generateResponse(string $query, string $systemPrompt): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Sarvam API key not configured (SARVAM_API_KEY)');
        }

        $response = Http::withHeaders([
            'api-subscription-key' => $this->apiKey,
            'Content-Type'         => 'application/json',
        ])->timeout(60)->post('https://api.sarvam.ai/v1/chat/completions', [
            'model'      => 'sarvam-105b',
            'max_tokens' => 2000,
            'messages'   => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $query],
            ],
            'temperature' => 0.2,
        ]);

        if ($response->failed()) {
            Log::error('Sarvam API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Sarvam API request failed');
        }

        return $response->json('choices.0.message.content', '');
    }
}
