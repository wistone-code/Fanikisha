<?php

namespace App\Services;

/** Shrinks phone photos with GD so they are cheap to store and quick to load on slow networks. */
class ImageResizer
{
    /** @return array{image: string, thumb: string}|null null when the file is not a usable image */
    public function process(string $bytes, int $maxSide = 1400, int $thumbSide = 360): ?array
    {
        $src = @imagecreatefromstring($bytes);

        if (! $src) {
            return null;
        }

        $src = $this->fixOrientation($src, $bytes);

        return [
            'image' => $this->encode($this->scale($src, $maxSide), 78),
            'thumb' => $this->encode($this->scale($src, $thumbSide), 70),
        ];
    }

    /** Resizes any image to fit inside $maxWidth, keeping PNG transparency; returns [bytes, mime, width, height]. */
    public function fit(string $bytes, int $maxWidth = 1080): ?array
    {
        $src = @imagecreatefromstring($bytes);

        if (! $src) {
            return null;
        }

        $src = $this->fixOrientation($src, $bytes);
        $w = imagesx($src);
        $h = imagesy($src);

        if ($w > $maxWidth) {
            $nh = (int) round($h * $maxWidth / $w);
            $dst = imagecreatetruecolor($maxWidth, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, $nh, $w, $h);
            $src = $dst;
            $w = $maxWidth;
            $h = $nh;
        }

        ob_start();
        imagepng($src, null, 7);
        $png = (string) ob_get_clean();

        // A photographic design is far smaller as JPEG; keep PNG only when it is already small.
        if (strlen($png) <= 700_000) {
            return [$png, 'image/png', $w, $h];
        }

        $flat = imagecreatetruecolor($w, $h);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
        ob_start();
        imagejpeg($flat, null, 85);

        return [(string) ob_get_clean(), 'image/jpeg', $w, $h];
    }

    private function scale($src, int $maxSide)
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $ratio = min(1, $maxSide / max($w, $h));

        if ($ratio >= 1) {
            return $src;
        }

        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private function encode($img, int $quality): string
    {
        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }

        ob_start();
        imagejpeg($img, null, $quality);

        return (string) ob_get_clean();
    }

    private function fixOrientation($img, string $bytes)
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($bytes, "\xFF\xD8")) {
            return $img;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($img, $angle, 0) ?: $img) : $img;
    }
}
