<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Groq (OpenAI-compatible chat completions) in JSON mode. Groq's JSON mode
 * guarantees a JSON object but takes no schema, so the expected schema is
 * included in the system message; the app validates the result anyway.
 */
class GroqClient extends JsonAiClient
{
    public function provider(): string
    {
        return 'groq';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.groq.key'));
    }

    public function model(): string
    {
        return (string) config('services.groq.model', 'llama-3.3-70b-versatile');
    }

    protected function retries(): int
    {
        return (int) config('services.groq.retries', 2);
    }

    protected function requestOnce(string $systemInstruction, string $prompt, array $responseSchema, float $temperature): array
    {
        $system = $systemInstruction
            ."\n\nOUTPUT FORMAT: respond with ONE JSON object only (no markdown, no commentary) that matches this JSON Schema exactly:\n"
            .json_encode($this->toJsonSchema($responseSchema), JSON_UNESCAPED_SLASHES);

        $payload = [
            'model' => $this->model(),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $temperature,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 8192,
        ];

        $url = rtrim((string) config('services.groq.base_url'), '/').'/chat/completions';

        try {
            $response = Http::withToken((string) config('services.groq.key'))
                ->acceptJson()
                ->timeout((int) config('services.groq.timeout', 60))
                ->connectTimeout(10)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new AiException(AiException::UNAVAILABLE, 'Connection to Groq failed: '.$e->getMessage(),
                'The AI service could not be reached. Please try again in a moment.');
        }

        $status = $response->status();
        $code = (string) data_get($response->json(), 'error.code', '');
        $detail = (string) data_get($response->json(), 'error.message', 'HTTP '.$status);

        if ($status === 429) {
            throw new AiException(AiException::RATE_LIMITED, 'Groq rate limit (429): '.$detail,
                'The AI service is busy (rate limit reached). Please wait a minute and try again.');
        }
        if ($response->serverError()) {
            throw new AiException(AiException::UNAVAILABLE, 'Groq server error '.$status,
                'The AI service is temporarily unavailable. Please try again shortly.');
        }
        if ($status === 400 && $code === 'json_validate_failed') {
            // The model produced invalid JSON; worth another attempt.
            throw new AiException(AiException::INVALID_RESPONSE, 'Groq JSON validation failed: '.$detail,
                'The AI returned an unreadable response. Please try again.');
        }
        if ($response->clientError()) {
            $user = match (true) {
                $status === 401 || $status === 403 || str_contains(strtolower($detail), 'api key') => 'The Groq API key was rejected. Check GROQ_API_KEY in the server environment.',
                $status === 404 || $code === 'model_not_found' || $code === 'model_decommissioned' => 'The Groq model "'.$this->model().'" is not available. Check GROQ_MODEL.',
                default => 'The AI service rejected the request.',
            };

            throw new AiException(AiException::REQUEST_REJECTED, 'Groq client error: '.$detail, $user);
        }

        $finish = data_get($response->json(), 'choices.0.finish_reason');
        $decoded = $this->decodeJson((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($decoded === null || $finish === 'length') {
            throw new AiException(AiException::INVALID_RESPONSE, 'Groq returned malformed or truncated JSON (finish: '.($finish ?? 'n/a').')',
                'The AI returned an unreadable response. Please try again.');
        }

        return $decoded;
    }

    /** Convert the Gemini-style schema (uppercase types) into standard JSON Schema. */
    public function toJsonSchema(array $schema): array
    {
        $out = [];
        foreach ($schema as $key => $value) {
            if ($key === 'type' && is_string($value)) {
                $out[$key] = strtolower($value);
            } elseif (is_array($value)) {
                $out[$key] = $key === 'required' || $key === 'enum' ? $value : $this->toJsonSchema($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
