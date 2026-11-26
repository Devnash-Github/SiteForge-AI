<?php

namespace App\Services\Research;

interface SearchProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /** @return SearchResult[] */
    public function search(string $query, int $limit): array;
}
