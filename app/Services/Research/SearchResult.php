<?php

namespace App\Services\Research;

final class SearchResult
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $snippet,
    ) {}
}
