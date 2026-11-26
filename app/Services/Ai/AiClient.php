<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;

/**
 * A language model that returns structured JSON. Implemented for Google
 * Gemini and Groq; the active provider is chosen in AppServiceProvider.
 */
interface AiClient
{
    /** "gemini" or "groq". */
    public function provider(): string;

    public function isConfigured(): bool;

    public function model(): string;

    /**
     * @param  array<string, mixed>  $responseSchema  OpenAPI-style object schema describing the expected JSON
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    public function generateJson(string $systemInstruction, string $prompt, array $responseSchema, float $temperature = 0.4): array;
}
