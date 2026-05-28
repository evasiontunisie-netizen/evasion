<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;

final class UploadController extends Controller
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/csv' => 'csv',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    public function store(Request $request): void
    {
        if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $this->error('File is required');
            return;
        }

        $file = $_FILES['file'];
        if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
            $this->error('File too large');
            return;
        }

        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        if (!isset(self::ALLOWED[$mime])) {
            $this->error('Unsupported file type');
            return;
        }

        $dir = BASE_PATH . '/storage/uploads/' . date('Y/m');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        $target = $dir . '/' . $filename;
        move_uploaded_file($file['tmp_name'], $target);

        $this->ok([
            'path' => str_replace(BASE_PATH . '/', '', $target),
            'mime' => $mime,
            'size' => (int) $file['size'],
            'original_name' => $file['name'] ?? $filename,
        ], 201);
    }
}
