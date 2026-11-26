<?php

namespace App\Http\Requests;

final class UploadRules
{
    /** Validation for one uploaded business file: extension AND detected content type must match. */
    public static function file(bool $imagesOnly = false): array
    {
        $max = (int) config('siteforge.max_upload_kb', 10240);

        return $imagesOnly
            ? ['file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', "max:{$max}"]
            : ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', "max:{$max}"];
    }

    public static function messages(): array
    {
        $mb = round(config('siteforge.max_upload_kb', 10240) / 1024);

        return [
            'files.*.mimes' => 'Only PDF, JPG, PNG and WebP files are supported.',
            'files.*.mimetypes' => 'This file’s contents do not match a supported type (PDF, JPG, PNG or WebP).',
            'files.*.max' => "Each file must be {$mb} MB or smaller.",
            'files.max' => 'You can upload up to :max files at a time.',
        ];
    }
}
