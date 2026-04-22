<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Down-scales JPEG/PNG/GIF/WEBP images so an MMS payload stays under most
 * US carriers' size cap (≈600 KB). Uses native GD — no extra Composer
 * dependency. If anything goes wrong (corrupt image, unsupported format),
 * returns the original bytes unchanged so the sender is never blocked.
 */
class ImageResizer
{
    /** Target ceiling in bytes for the resized output. */
    public const TARGET_MAX_BYTES = 500 * 1024;
    /** Max longest-side dimension we'll allow after the first pass. */
    public const TARGET_MAX_DIM   = 1600;

    /**
     * @return array{bytes:string,mime:string,extension:string,resized:bool}
     */
    public function fitForMms(string $bytes, string $originalMime): array
    {
        $unchanged = ['bytes' => $bytes, 'mime' => $originalMime, 'extension' => $this->extFor($originalMime), 'resized' => false];

        if (strlen($bytes) <= self::TARGET_MAX_BYTES)             return $unchanged;
        if (!str_starts_with($originalMime, 'image/'))            return $unchanged;
        if (!extension_loaded('gd'))                              return $unchanged;

        try {
            $src = @imagecreatefromstring($bytes);
            if (!$src) return $unchanged;

            // First, scale dimensions if the image is larger than TARGET_MAX_DIM
            [$w, $h] = [imagesx($src), imagesy($src)];
            $longest = max($w, $h);
            if ($longest > self::TARGET_MAX_DIM) {
                $scale = self::TARGET_MAX_DIM / $longest;
                $newW  = (int) round($w * $scale);
                $newH  = (int) round($h * $scale);
                $resized = imagecreatetruecolor($newW, $newH);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagedestroy($src);
                $src = $resized;
            }

            // Now drop JPEG quality progressively until under the cap (or quality 40)
            $quality = 85;
            $out = $this->encodeJpeg($src, $quality);
            while (strlen($out) > self::TARGET_MAX_BYTES && $quality > 40) {
                $quality -= 10;
                $out = $this->encodeJpeg($src, $quality);
            }
            // Last resort: scale down again if still too big
            if (strlen($out) > self::TARGET_MAX_BYTES) {
                [$w, $h] = [imagesx($src), imagesy($src)];
                $newW = (int) round($w * 0.7);
                $newH = (int) round($h * 0.7);
                $smaller = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($smaller, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagedestroy($src);
                $src = $smaller;
                $out = $this->encodeJpeg($src, 70);
            }

            imagedestroy($src);
            return ['bytes' => $out, 'mime' => 'image/jpeg', 'extension' => 'jpg', 'resized' => true];
        } catch (\Throwable $e) {
            \Log::warning('ImageResizer failed, sending original', ['error' => $e->getMessage()]);
            return $unchanged;
        }
    }

    private function encodeJpeg($img, int $quality): string
    {
        ob_start();
        imagejpeg($img, null, $quality);
        return (string) ob_get_clean();
    }

    private function extFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'bin',
        };
    }
}
