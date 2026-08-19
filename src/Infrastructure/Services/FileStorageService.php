<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Services;

use Libok\Application\Contracts\MalwareScannerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileStorageService
{
    /** Directories that may be served publicly (also written under public storage). */
    public const PUBLIC_DIRECTORIES = ['avatars', 'uploads'];

    /** @var array<string, list<string>> */
    private const ALLOWED_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mp3' => ['audio/mpeg'],
        'ogg' => ['audio/ogg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'txt' => ['text/plain'],
    ];

    private string $storagePath;
    private string $publicStoragePath;
    private int $maxUploadBytes;

    public function __construct(private readonly MalwareScannerInterface $scanner)
    {
        $raw = rtrim((string) ($_ENV['STORAGE_PATH'] ?? (defined('LIBOK_STORAGE') ? LIBOK_STORAGE : '')), '/\\');
        $this->storagePath = realpath($raw) ?: $raw;

        $publicRaw = rtrim(
            (string) ($_ENV['PUBLIC_STORAGE_PATH'] ?? (LIBOK_ROOT . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage')),
            '/\\',
        );
        $this->publicStoragePath = realpath($publicRaw) ?: $publicRaw;

        $this->maxUploadBytes = ((int) ($_ENV['MAX_UPLOAD_MB'] ?? 20)) * 1024 * 1024;
    }

    /**
     * Store an uploaded file under the given relative directory.
     * Returns the relative path from the storage root that was used.
     */
    public function store(UploadedFile $file, string $directory, ?string $allowedMimePattern = null): string
    {
        $this->validateFile($file, $allowedMimePattern);

        $directory = trim($directory, '/\\');
        $fullDir = $this->buildFullPath($directory);

        if (!is_dir($fullDir) && !mkdir($fullDir, 0750, true) && !is_dir($fullDir)) {
            throw new \RuntimeException('Unable to create the storage directory.');
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) ($file->guessExtension() ?? ''));
        }
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $originalName) ?: 'file';
        $filename = sprintf('%s_%s.%s', $safeName, bin2hex(random_bytes(8)), $extension);

        $this->scanPath((string) $file->getPathname(), $directory . '/' . $filename);
        $file->move($fullDir, $filename);

        return $directory . '/' . $filename;
    }

    /**
     * Resolves a relative storage path to an absolute path using the
     * canonical storage root (normalises separators on all platforms).
     */
    public function getFullPath(string $relativePath): string
    {
        return $this->buildFullPath($relativePath);
    }

    public function storeFromContent(string $content, string $relativePath): void
    {
        $fullPath = $this->buildFullPath($relativePath);
        $dir = dirname($fullPath);

        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create the storage directory.');
        }

        if (file_put_contents($fullPath, $content) === false) {
            throw new \RuntimeException('Unable to write the storage file.');
        }
    }

    public function read(string $relativePath): string
    {
        $fullPath = $this->buildFullPath($relativePath);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException('File not found.');
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new \RuntimeException('Unable to read the storage file.');
        }

        return $content;
    }

    public function delete(string $relativePath): void
    {
        $fullPath = $this->buildFullPath($relativePath);

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function exists(string $relativePath): bool
    {
        return file_exists($this->buildFullPath($relativePath));
    }

    public function getMimeType(string $relativePath): string
    {
        $fullPath = $this->buildFullPath($relativePath);
        $mime = mime_content_type($fullPath);

        return $mime ?: 'application/octet-stream';
    }

    /** Public web path for a stored relative file (e.g. avatars/a.png → /storage/avatars/a.png). */
    public function publicUrl(string $relativePath): ?string
    {
        $normalised = ltrim(str_replace('\\', '/', $relativePath), '/');
        $first = explode('/', $normalised)[0] ?? '';
        if (!in_array($first, self::PUBLIC_DIRECTORIES, true)) {
            return null;
        }

        return '/storage/' . $normalised;
    }

    private function buildFullPath(string $relativePath): string
    {
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            throw new \InvalidArgumentException('Invalid storage path.');
        }

        $normalised = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (
            str_starts_with($normalised, DIRECTORY_SEPARATOR)
            || preg_match('/^[a-zA-Z]:[\\\\\/]/', $relativePath) === 1
        ) {
            throw new \InvalidArgumentException('Storage path must be relative.');
        }

        $segments = explode(DIRECTORY_SEPARATOR, $normalised);
        $safeSegments = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new \InvalidArgumentException('Storage path cannot leave the storage root.');
            }
            $safeSegments[] = $segment;
        }

        if ($safeSegments === []) {
            throw new \InvalidArgumentException('Invalid storage path.');
        }

        $root = $this->rootForDirectory($safeSegments[0]);

        return $root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $safeSegments);
    }

    private function rootForDirectory(string $directory): string
    {
        return in_array($directory, self::PUBLIC_DIRECTORIES, true)
            ? $this->publicStoragePath
            : $this->storagePath;
    }

    private function validateFile(UploadedFile $file, ?string $allowedMimePattern): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('The uploaded file is invalid or corrupted.');
        }

        if ($file->getSize() > $this->maxUploadBytes) {
            $maxMb = $this->maxUploadBytes / 1024 / 1024;
            throw new \InvalidArgumentException("The file is too large. Maximum size is {$maxMb} MB.");
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '' || !isset(self::ALLOWED_BY_EXTENSION[$extension])) {
            throw new \InvalidArgumentException('File type is not allowed.');
        }

        $allowedMimes = self::ALLOWED_BY_EXTENSION[$extension];
        $detected = $this->normalizeMime(mime_content_type($file->getPathname()) ?: null);

        if (!in_array($detected, $allowedMimes, true)) {
            throw new \InvalidArgumentException('File type is not allowed.');
        }

        if ($allowedMimePattern !== null && !preg_match($allowedMimePattern, $detected)) {
            throw new \InvalidArgumentException('File type is not allowed for this context.');
        }
    }

    private function normalizeMime(?string $mime): string
    {
        $mime = strtolower(trim((string) $mime));
        $semi = strpos($mime, ';');
        if ($semi !== false) {
            $mime = trim(substr($mime, 0, $semi));
        }

        return $mime;
    }

    private function scanPath(string $path, string $key): void
    {
        if ($path === '' || !is_file($path)) {
            return;
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to scan the uploaded file.');
        }
        try {
            $this->scanner->assertClean($stream, $key);
        } finally {
            fclose($stream);
        }
    }
}
