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

    /**
     * Convert browser-recorded webm/ogg audio to AAC (m4a). Carriers typically
     * accept M4A/AMR/3GPP for MMS audio; webm is a web-only format and gets
     * stripped silently. Requires ffmpeg on the server (prod has it).
     *
     * @return array{bytes:string,mime:string,extension:string,converted:bool}
     */
    public function convertAudioForMms(string $bytes, string $originalMime): array
    {
        $unchanged = ['bytes' => $bytes, 'mime' => $originalMime, 'extension' => $this->audioExtFor($originalMime), 'converted' => false];

        // Only need to convert browser-only formats; carriers handle mp3/m4a/amr/3gp natively
        if (!in_array($originalMime, ['audio/webm', 'audio/ogg', 'video/webm'], true)) return $unchanged;

        $ffmpeg = trim((string) shell_exec('which ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') return $unchanged;

        try {
            $tmpIn  = tempnam(sys_get_temp_dir(), 'sms-in-')  . '.webm';
            $tmpOut = tempnam(sys_get_temp_dir(), 'sms-out-') . '.mp3';
            file_put_contents($tmpIn, $bytes);
            // MP3 mono 44.1kHz 64kbps — sniffed live from Telebroad's own
            // web UI (file `voice_note_MM_DD_YYYY_HH_MM_SS.mp3`, magic
            // bytes ff fb). Phones display MP3 voice with the proper
            // waveform + voice-note UI; M4A/3GP fall through to a
            // generic video/media player.
            $cmd = escapeshellcmd($ffmpeg) . ' -y -i ' . escapeshellarg($tmpIn)
                 . ' -vn -ac 1 -ar 44100 -c:a libmp3lame -b:a 64k '
                 . escapeshellarg($tmpOut) . ' 2>&1';
            $out = shell_exec($cmd);
            $converted = @file_get_contents($tmpOut);
            @unlink($tmpIn); @unlink($tmpOut);
            if (!$converted) {
                \Log::warning('Audio convert failed', ['ffmpeg_output' => substr((string) $out, -300)]);
                return $unchanged;
            }
            return ['bytes' => $converted, 'mime' => 'audio/mpeg', 'extension' => 'mp3', 'converted' => true];
        } catch (\Throwable $e) {
            \Log::warning('Audio convert exception', ['error' => $e->getMessage()]);
            return $unchanged;
        }
    }

    private function audioExtFor(string $mime): string
    {
        return match ($mime) {
            'audio/mp4'   => 'm4a',
            'audio/mpeg'  => 'mp3',
            'audio/wav'   => 'wav',
            'audio/webm'  => 'webm',
            'audio/ogg'   => 'ogg',
            'audio/3gpp'  => '3gp',
            'audio/amr'   => 'amr',
            default       => 'bin',
        };
    }
}
