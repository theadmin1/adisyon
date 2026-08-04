<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class ProductImageService
{
    public function toPersistentDataUri(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Ürün görseli okunamadı.');
        }

        if (! function_exists('imagecreatefromstring')) {
            return 'data:'.($file->getMimeType() ?: 'image/jpeg').';base64,'.base64_encode($contents);
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new RuntimeException('Ürün görseli işlenemedi.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 900 / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($target, null, 78);
            $mime = 'image/webp';
        } else {
            imagejpeg($target, null, 80);
            $mime = 'image/jpeg';
        }
        $encoded = ob_get_clean();
        imagedestroy($target);

        if ($encoded === false || $encoded === '') {
            throw new RuntimeException('Ürün görseli sıkıştırılamadı.');
        }

        return 'data:'.$mime.';base64,'.base64_encode($encoded);
    }
}
