<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by the AI layer. $userMessage is safe to show; the exception
 * message may contain technical details and is only logged.
 */
class AiException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const RATE_LIMITED = 'rate_limited';

    public const UNAVAILABLE = 'unavailable';

    public const INVALID_RESPONSE = 'invalid_response';

    public const REQUEST_REJECTED = 'request_rejected';

    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly string $userMessage = 'The AI service could not complete the request. Please try again.',
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(self::NOT_CONFIGURED, 'No AI API key is configured.',
            'AI is not configured. Add GEMINI_API_KEY or GROQ_API_KEY to the server environment to enable AI writing.');
    }
}
