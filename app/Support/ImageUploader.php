<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\HttpException;

final class ImageUploader
{
    public function uploadResultProof(array $file, string $prefix): array
    {
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new HttpException($this->uploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)), 422);
        }
        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > MAX_FILE_SIZE) {
            throw new HttpException('Screenshot must be no larger than 5 MB.', 422);
        }

        $temporary = (string) ($file['tmp_name'] ?? '');
        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw new HttpException('The screenshot upload is invalid.', 422);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($temporary);
        if (!in_array($mime, ALLOWED_TYPES, true) || getimagesize($temporary) === false) {
            throw new HttpException('Only valid JPG, PNG, or WEBP images are accepted.', 422);
        }
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new HttpException('Unsupported screenshot type.', 422),
        };
        $originalExtension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($originalExtension, ALLOWED_EXTS, true)) {
            throw new HttpException('The screenshot filename has an invalid extension.', 422);
        }

        $directory = UPLOAD_DIR . 'results/';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new HttpException('The server could not prepare screenshot storage.', 500);
        }
        $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix) ?: 'result';
        $filename = $safePrefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $path = $directory . $filename;

        if (!$this->normalizeImage($temporary, $path, $mime) && !move_uploaded_file($temporary, $path)) {
            throw new HttpException('The screenshot could not be saved.', 500);
        }
        @chmod($path, 0644);
        return ['filename' => $filename, 'path' => $path, 'url' => UPLOAD_URL . 'results/' . rawurlencode($filename)];
    }

    public function deleteByUrl(?string $url): void
    {
        if (!$url) return;
        $filename = basename((string) parse_url($url, PHP_URL_PATH));
        if (!preg_match('/^[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$/i', $filename)) return;
        $path = UPLOAD_DIR . 'results/' . $filename;
        if (is_file($path)) @unlink($path);
    }

    private function normalizeImage(string $source, string $destination, string $mime): bool
    {
        if (!function_exists('imagecreatefromjpeg')) return false;
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if (!$image) return false;
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $destination, 88),
            'image/png' => imagepng($image, $destination, 7),
            'image/webp' => function_exists('imagewebp') && imagewebp($image, $destination, 88),
            default => false,
        };
        imagedestroy($image);
        return (bool) $saved;
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Screenshot is larger than the server limit.',
            UPLOAD_ERR_PARTIAL => 'Screenshot upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE => 'Screenshot proof is required.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server upload storage is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the screenshot.',
            default => 'Screenshot upload failed.',
        };
    }
}

