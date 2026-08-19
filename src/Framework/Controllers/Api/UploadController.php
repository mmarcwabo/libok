<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers\Api;

use Libok\Framework\Controllers\BaseController;
use Libok\Infrastructure\Services\FileStorageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends BaseController
{
    public function __construct(private readonly FileStorageService $fileStorage)
    {
    }

    public function store(Request $request): Response
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->error('A file is required.', 400);
        }

        $directory = (string) $request->request->get('directory', 'uploads');

        try {
            $relative = $this->fileStorage->store($file, $directory);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422, 'upload.rejected');
        }

        return $this->json([
            'path' => $relative,
            'url' => $this->fileStorage->publicUrl($relative),
        ], 201, 'File stored.');
    }
}
