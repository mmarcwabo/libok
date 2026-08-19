<?php

declare(strict_types=1);

namespace Libok\Framework\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Application\Contracts\ObjectStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HealthController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ObjectStorageInterface $storage,
    ) {
    }

    public function live(Request $request): Response
    {
        return $this->json(['status' => 'ok']);
    }

    public function ready(Request $request): Response
    {
        $checks = ['database' => false, 'storage' => false];
        $errors = [];
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1')->fetchOne();
            $checks['database'] = true;
        } catch (\Throwable $e) {
            $errors['database'] = $e->getMessage();
        }
        try {
            $checks['storage'] = $this->storage->isReady();
        } catch (\Throwable $e) {
            $errors['storage'] = $e->getMessage();
        }

        $ready = !in_array(false, $checks, true);
        $payload = [
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => array_map(static fn (bool $ok): string => $ok ? 'ok' : 'failed', $checks),
            'driver' => (string) ($_ENV['DB_DRIVER'] ?? 'pdo_pgsql'),
        ];
        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($debug && $errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($ready) {
            return $this->json($payload);
        }

        return new Response(
            json_encode([
                'success' => false,
                'message' => 'Service unavailable.',
                'code' => 'health.not_ready',
                'data' => $payload,
            ], JSON_UNESCAPED_SLASHES),
            503,
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-store',
            ]
        );
    }
}
