<?php

return [

    /*
    | Disk used for uploaded business files (PDFs and images). Files are
    | never served directly from the disk: images are streamed through an
    | authorised route, so a private disk ("local") is the safe default.
    */
    'media_disk' => env('SITEFORGE_MEDIA_DISK', 'local'),

    // Maximum size of a single upload, in kilobytes.
    'max_upload_kb' => (int) env('SITEFORGE_MAX_UPLOAD_KB', 10240),

    // Maximum files accepted in one upload request.
    'max_files_per_request' => 20,

    'allowed_upload_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],

    'fetch' => [
        'timeout' => (int) env('SITEFORGE_FETCH_TIMEOUT', 15),
        'max_bytes' => 2 * 1024 * 1024,
        'max_redirects' => 4,
        // Extra same-site pages (about/contact/services) crawled per website source.
        'max_extra_pages' => 3,
        'user_agent' => 'SiteForgeAI/1.0 (+business website builder; respects robots and login walls)',
        // Set true only in local development to allow fetching private network hosts.
        'allow_private_hosts' => (bool) env('SITEFORGE_ALLOW_PRIVATE_HOSTS', false),
    ],

    'pdf' => [
        'max_pages' => 60,
        'max_chars' => 60000,
    ],

    // Maximum characters of one source sent to the AI for fact extraction.
    'ai_source_char_limit' => 15000,

    'reserved_slugs' => ['admin', 'api', 'login', 'register', 'dashboard', 'preview', 'assets', 'build', 'storage'],
];
