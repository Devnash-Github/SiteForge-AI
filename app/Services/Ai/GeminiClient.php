<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini generateContent REST API with JSON output and a response
 * schema. The key is sent in a header, never in the URL.
 */
class GeminiClient extends JsonAiClient
{
    public function provider(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function model(): string
    {
        return (string) config('services.gemini.model', 'gemini-2.5-flash');
    }

    protected function retries(): int
    {
        return (int) config('services.gemini.retries', 2);
    }

    protected function requestOnce(string $systemInstruction, string $prompt, array $responseSchema, float $temperature): array
    {
        $generationConfig = [
            'temperature' => $temperature,
            'responseMimeType' => 'application/json',
            'responseSchema' => $responseSchema,
            'maxOutputTokens' => 16384,
        ];
        // 2.5 "flash" models spend output tokens on thinking; turn it off so answers are not cut short.
        if (str_contains($this->model(), 'flash')) {
            $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => $generationConfig,
        ];

        $url = rtrim((string) config('services.gemini.base_url'), '/').'/models/'.rawurlencode($this->model()).':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->acceptJson()
                ->timeout((int) config('services.gemini.timeout', 60))
                ->connectTimeout(10)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new AiException(AiException::UNAVAILABLE, 'Connection to Gemini failed: '.$e->getMessage(),
                'The AI service could not be reached. Please try again in a moment.');
        }

        if ($response->status() === 429) {
            throw new AiException(AiException::RATE_LIMITED, 'Gemini rate limit (429)',
                'The AI service is busy (rate limit reached). Please wait a minute and try again.');
        }

        if ($response->serverError()) {
            throw new AiException(AiException::UNAVAILABLE, 'Gemini server error '.$response->status(),
                'The AI service is temporarily unavailable. Please try again shortly.');
        }

        if ($response->clientError()) {
            $detail = (string) data_get($response->json(), 'error.message', 'HTTP '.$response->status());
            $user = match (true) {
                in_array($response->status(), [401, 403], true) || str_contains(strtolower($detail), 'api key') => 'The Gemini API key was rejected. Check GEMINI_API_KEY in the server environment.',
                $response->status() === 404 => 'The Gemini model "'.$this->model().'" is not available for this key. Check GEMINI_MODEL.',
                default => 'The AI service rejected the request.',
            };

            throw new AiException(AiException::REQUEST_REJECTED, 'Gemini client error: '.$detail, $user);
        }

        $data = $response->json();
        $finish = data_get($data, 'candidates.0.finishReason');
        if (data_get($data, 'promptFeedback.blockReason') || in_array($finish, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true)) {
            throw new AiException(AiException::REQUEST_REJECTED, 'Gemini blocked the response: '.($finish ?? data_get($data, 'promptFeedback.blockReason')),
                'The AI declined to write this content. Try rephrasing your instructions.');
        }

        $text = collect(data_get($data, 'candidates.0.content.parts', []))->pluck('text')->filter()->implode('');
        $decoded = $this->decodeJson($text);
        if ($decoded === null) {
            throw new AiException(AiException::INVALID_RESPONSE, 'Gemini returned malformed JSON (finish: '.($finish ?? 'n/a').')',
                'The AI returned an unreadable response. Please try again.');
        }

        return $decoded;
    }
}
