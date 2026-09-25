<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Files people give us — signatures, scanned forms, physician letters.
 *
 * They live under the private directory, never the document root, and are
 * only ever streamed to a signed-in person who is allowed to see them.
 */

const UPLOAD_MAX_BYTES = 10 * 1024 * 1024;
const SIGNATURE_MAX_BYTES = 300 * 1024;
const UPLOAD_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/heic' => 'heic'];

function uploads_dir(): string
{
    return rtrim((string) cfg('uploads_dir', cfg('private_dir', '/var/www/private/sanatecdiving') . '/uploads'), '/');
}

/** Absolute path for a stored relative path, refusing anything that escapes the directory. */
function upload_path(string $relative): ?string
{
    $base = realpath(uploads_dir());
    $full = realpath(uploads_dir() . '/' . ltrim($relative, '/'));

    return $base !== false && $full !== false && str_starts_with($full, $base . '/') ? $full : null;
}

/** Store a drawn signature (a PNG data URL from the canvas). Returns the relative path. */
function store_signature(string $dataUrl, string $name): string
{
    if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) !== 1) {
        throw new InvalidArgumentException('Please sign in the box before continuing.');
    }
    $png = base64_decode($m[1], true);
    if ($png === false || strlen($png) < 100 || strlen($png) > SIGNATURE_MAX_BYTES || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
        throw new InvalidArgumentException('That signature could not be read. Please sign again.');
    }
    // A blank canvas still encodes to a small PNG; a real signature has ink.
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring($png);
        if ($img !== false && !image_has_ink($img)) {
            throw new InvalidArgumentException('Please sign in the box before continuing.');
        }
    }

    return write_upload('signatures', $name . '.png', $png);
}

/** Does the image contain any non-transparent, non-white pixel? Sampled, so it stays cheap. */
function image_has_ink(GdImage $img): bool
{
    $w = imagesx($img);
    $h = imagesy($img);
    for ($y = 0; $y < $h; $y += 3) {
        for ($x = 0; $x < $w; $x += 3) {
            $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
            if ($c['alpha'] < 120 && ($c['red'] + $c['green'] + $c['blue']) < 600) {
                return true;
            }
        }
    }

    return false;
}

/** Store a browser upload ($_FILES entry). Returns the relative path. */
function store_upload(array $file, string $folder, string $name): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('The file did not upload. Try again.');
    }
    if ((int) $file['size'] > UPLOAD_MAX_BYTES) {
        throw new InvalidArgumentException('That file is over 10 MB.');
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(UPLOAD_TYPES[$mime])) {
        throw new InvalidArgumentException('Only PDF, JPEG, PNG or HEIC files are accepted.');
    }

    return write_upload($folder, $name . '.' . UPLOAD_TYPES[$mime], (string) file_get_contents($file['tmp_name']));
}

function write_upload(string $folder, string $filename, string $bytes): string
{
    $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder) ?: 'misc';
    $filename = preg_replace('/[^a-z0-9._-]/i', '_', $filename) ?: 'file';
    $dir = uploads_dir() . '/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('The upload directory is not writable.');
    }
    $relative = $folder . '/' . $filename;
    if (file_put_contents(uploads_dir() . '/' . $relative, $bytes, LOCK_EX) === false) {
        throw new RuntimeException('Could not store the file.');
    }
    chmod(uploads_dir() . '/' . $relative, 0640);

    return $relative;
}

/** Stream a stored file to the browser. */
function send_upload(string $relative, string $downloadName = ''): never
{
    $path = upload_path($relative);
    if ($path === null) {
        http_response_code(404);
        exit('Not found.');
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . ($downloadName ?: basename($path)) . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
