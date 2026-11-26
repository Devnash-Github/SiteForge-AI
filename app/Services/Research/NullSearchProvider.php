<?php

namespace App\Services\Research;

class NullSearchProvider implements SearchProvider
{
    public function name(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function search(string $query, int $limit): array
    {
        return [];
    }
}
