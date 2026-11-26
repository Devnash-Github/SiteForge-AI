<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use Illuminate\Support\Facades\Log;

/** Shared retry loop and defensive JSON decoding for every AI provider. */
abstract class JsonAiClient implements AiClient
{
    /** One HTTP attempt; throws AiException on any failure. */
    abstract protected function requestOnce(string $systemInstruction, string $prompt, array $responseSchema, float $temperature): array;

    abstract protected function retries(): int;

    public function generateJson(string $systemInstruction, string $prompt, array $responseSchema, float $temperature = 0.4): array
    {
        if (! $this->isConfigured()) {
            throw AiException::notConfigured();
        }

        $attempts = max(1, $this->retries() + 1);
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->requestOnce($systemInstruction, $prompt, $responseSchema, $temperature);
            } catch (AiException $e) {
                $lastError = $e;
                $retryable = in_array($e->reason, [AiException::RATE_LIMITED, AiException::UNAVAILABLE, AiException::INVALID_RESPONSE], true);
                Log::warning('AI request failed', ['provider' => $this->provider(), 'attempt' => $attempt, 'reason' => $e->reason, 'detail' => $e->getMessage()]);
                if (! $retryable || $attempt === $attempts) {
                    break;
                }
                $this->pause($attempt, $e->reason);
            }
        }

        throw $lastError ?? new AiException(AiException::UNAVAILABLE, 'Unknown AI failure');
    }

    /** @return array<string, mixed>|null */
    public function decodeJson(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $m)) {
            $text = $m[1];
        }
        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            }
        }

        return is_array($decoded) ? $decoded : null;
    }

    protected function pause(int $attempt, string $reason): void
    {
        $base = (int) config('services.ai.retry_delay_ms', 1500);
        $ms = $base * $attempt * ($reason === AiException::RATE_LIMITED ? 3 : 1);
        if ($ms > 0) {
            usleep(min($ms, 15000) * 1000);
        }
    }
}
