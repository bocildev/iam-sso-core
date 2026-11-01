<?php

namespace App\Domain\Storage;

class ImageCompressorService
{
    /** Batas maksimal target ukuran gambar setelah kompresi (500 KB). */
    public const TARGET_MAX_BYTES = 512000; // 500 * 1024 bytes

    /** Batas maksimal dimensi gambar untuk web (1920px). */
    public const MAX_DIMENSION = 1920;

    /**
     * Memeriksa apakah file adalah gambar raster yang dapat diproses oleh GD.
     */
    public function canCompress(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ], true);
    }

    /**
     * Kompres gambar ke ukuran <= 500 KB.
     * Mengembalikan informasi file hasil kompresi (path, size, mime, compressed flag).
     *
     * @return array{temp_path: string, size: int, mime_type: string, extension: string, is_compressed: bool}
     */
    public function compress(string $sourcePath, string $mimeType, int $targetMaxBytes = self::TARGET_MAX_BYTES): array
    {
        $currentSize = filesize($sourcePath);

        // Jika ukuran sudah di bawah 500 KB, tidak perlu kompresi ulang
        if ($currentSize <= $targetMaxBytes || ! $this->canCompress($mimeType)) {
            return [
                'temp_path' => $sourcePath,
                'size' => $currentSize,
                'mime_type' => $mimeType,
                'extension' => $this->extensionFromMime($mimeType),
                'is_compressed' => false,
            ];
        }

        // Muat gambar ke GD resource
        $image = $this->createGdImage($sourcePath, $mimeType);
        if (! $image) {
            // Jika GD gagal membaca, kembalikan file asli
            return [
                'temp_path' => $sourcePath,
                'size' => $currentSize,
                'mime_type' => $mimeType,
                'extension' => $this->extensionFromMime($mimeType),
                'is_compressed' => false,
            ];
        }

        // Periksa dan sesuaikan dimensi jika terlalu besar (> 1920px)
        $origW = imagesx($image);
        $origH = imagesy($image);
        $image = $this->resizeIfNeeded($image, $origW, $origH, self::MAX_DIMENSION);

        $tempFile = tempnam(sys_get_temp_dir(), 'iam_img_');

        // Strategi kompresi bertingkat
        $hasAlpha = $this->hasTransparency($mimeType);
        $targetExt = $this->extensionFromMime($mimeType);
        $outputMime = $mimeType;

        // Loop kompresi: coba dari kualitas tinggi (85) sampai (50)
        $qualities = [85, 75, 65, 55, 45];
        $bestFile = null;
        $bestSize = $currentSize;

        foreach ($qualities as $q) {
            if ($mimeType === 'image/jpeg') {
                imagejpeg($image, $tempFile, $q);
            } elseif ($mimeType === 'image/webp') {
                imagewebp($image, $tempFile, $q);
            } elseif ($mimeType === 'image/png') {
                if ($hasAlpha) {
                    // PNG dengan transparansi: kompresi level 9
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    imagepng($image, $tempFile, 9);
                    $fileSize = filesize($tempFile);
                    if ($fileSize > $targetMaxBytes && function_exists('imagewebp')) {
                        // Jika PNG transparan masih > 500 KB, konversi ke WebP lossless/lossy mempertahankan alpha
                        imagewebp($image, $tempFile, $q);
                        $outputMime = 'image/webp';
                        $targetExt = 'webp';
                    }
                } else {
                    // PNG tanpa transparansi: simpan ke JPEG atau WebP agar ukuran turun drastis
                    if (function_exists('imagewebp')) {
                        imagewebp($image, $tempFile, $q);
                        $outputMime = 'image/webp';
                        $targetExt = 'webp';
                    } else {
                        imagejpeg($image, $tempFile, $q);
                        $outputMime = 'image/jpeg';
                        $targetExt = 'jpg';
                    }
                }
            } else {
                // GIF atau lainnya
                if (function_exists('imagewebp')) {
                    imagewebp($image, $tempFile, $q);
                    $outputMime = 'image/webp';
                    $targetExt = 'webp';
                } else {
                    imagejpeg($image, $tempFile, $q);
                    $outputMime = 'image/jpeg';
                    $targetExt = 'jpg';
                }
            }

            clearstatcache(true, $tempFile);
            $newSize = filesize($tempFile);

            if ($newSize <= $targetMaxBytes) {
                $bestSize = $newSize;
                break;
            }

            // Jika masih lebih besar dari targetMaxBytes dan kualitas sudah rendah, coba perkecil dimensi lagi
            if ($q === 55 && $newSize > $targetMaxBytes) {
                $currW = imagesx($image);
                $currH = imagesy($image);
                $image = $this->resizeIfNeeded($image, $currW, $currH, 1280);
            }
        }

        imagedestroy($image);
        clearstatcache(true, $tempFile);
        $finalSize = filesize($tempFile);

        return [
            'temp_path' => $tempFile,
            'size' => $finalSize,
            'mime_type' => $outputMime,
            'extension' => $targetExt,
            'is_compressed' => true,
        ];
    }

    private function createGdImage(string $path, string $mime): mixed
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => null,
        };
    }

    private function resizeIfNeeded(mixed $image, int $w, int $h, int $maxDim): mixed
    {
        if ($w <= $maxDim && $h <= $maxDim) {
            return $image;
        }

        $scale = min($maxDim / $w, $maxDim / $h);
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($newW, $newH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        // Pertahankan transparansi background jika ada
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newW, $newH, $transparent);

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($image);

        return $canvas;
    }

    private function hasTransparency(string $mime): bool
    {
        return in_array($mime, ['image/png', 'image/webp', 'image/gif'], true);
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}
