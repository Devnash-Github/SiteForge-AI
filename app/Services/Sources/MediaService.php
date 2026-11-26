<?php

namespace App\Services\Sources;

use App\Models\Business;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaService
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function storeImage(Business $business, UploadedFile $file): Media
    {
        $mime = $file->getMimeType();
        $info = @getimagesize($file->getRealPath());
        if (! in_array($mime, self::IMAGE_MIMES, true) || $info === false) {
            throw ValidationException::withMessages(['files' => "“{$file->getClientOriginalName()}” is not a valid JPG, PNG or WebP image."]);
        }

        $disk = (string) config('siteforge.media_disk');
        $ext = match ($mime) {
            'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg'
        };
        $path = "businesses/{$business->id}/images/".Str::uuid().'.'.$ext;

        $bytes = $this->reencode($file->getRealPath(), $mime) ?? file_get_contents($file->getRealPath());
        Storage::disk($disk)->put($path, $bytes);

        return $business->media()->create([
            'disk' => $disk,
            'file_path' => $path,
            'file_name' => Str::limit($this->safeName($file->getClientOriginalName()), 200, ''),
            'mime_type' => $mime,
            'size' => strlen($bytes),
            'width' => $info[0] ?? null,
            'height' => $info[1] ?? null,
            'alt_text' => Str::limit(Str::headline(pathinfo($this->safeName($file->getClientOriginalName()), PATHINFO_FILENAME)), 120, ''),
        ]);
    }

    /** @return array{path: string, name: string, disk: string} */
    public function storeDocument(Business $business, UploadedFile $file): array
    {
        $disk = (string) config('siteforge.media_disk');
        $path = $file->storeAs("businesses/{$business->id}/documents", Str::uuid().'.pdf', $disk);

        return ['path' => $path, 'name' => Str::limit($this->safeName($file->getClientOriginalName()), 200, ''), 'disk' => $disk];
    }

    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->file_path);
        $media->delete();
    }

    public function deleteBusinessFiles(Business $business): void
    {
        foreach (array_unique(array_filter([config('siteforge.media_disk'), 'local', 'public'])) as $disk) {
            Storage::disk($disk)->deleteDirectory("businesses/{$business->id}");
        }
    }

    /**
     * Re-encode images with GD: strips metadata and anything appended to the
     * file (polyglot payloads). Returns null if GD cannot handle the format.
     */
    private function reencode(string $path, string $mime): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            return null;
        }
        ob_start();
        $ok = match ($mime) {
            'image/png' => (function () use ($image) {
                imagesavealpha($image, true);

                return imagepng($image, null, 6);
            })(),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 85) : false,
            default => imagejpeg($image, null, 85),
        };
        $data = ob_get_clean();
        imagedestroy($image);

        return $ok && $data ? $data : null;
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[^\pL\pN._ -]+/u', '', $name) ?? 'file';

        return trim($name) !== '' ? trim($name) : 'file';
    }
}
