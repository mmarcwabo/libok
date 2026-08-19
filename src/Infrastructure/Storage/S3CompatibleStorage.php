<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Storage;

use Libok\Application\Contracts\MalwareScannerInterface;
use Libok\Application\Contracts\ObjectStorageInterface;

/**
 * Optional adapter for aws/aws-sdk-php. The SDK is deliberately not a default
 * dependency; install it when STORAGE_DRIVER=s3.
 */
final class S3CompatibleStorage implements ObjectStorageInterface
{
    /** @param object $client Aws\S3\S3Client */
    public function __construct(
        private readonly object $client,
        private readonly string $bucket,
        private readonly MalwareScannerInterface $scanner,
    ) {
        $s3Client = 'Aws\\S3\\S3Client';
        if (!class_exists($s3Client) || !$client instanceof $s3Client) {
            throw new \RuntimeException('S3 storage requires composer package aws/aws-sdk-php.');
        }
    }

    public static function fromEnvironment(MalwareScannerInterface $scanner): self
    {
        $s3Client = 'Aws\\S3\\S3Client';
        if (!class_exists($s3Client)) {
            throw new \RuntimeException('S3 storage requires composer package aws/aws-sdk-php.');
        }

        $bucket = (string) ($_ENV['S3_BUCKET'] ?? '');
        if ($bucket === '') {
            throw new \RuntimeException('S3_BUCKET is required when STORAGE_DRIVER=s3.');
        }

        $config = [
            'version' => 'latest',
            'region' => (string) ($_ENV['S3_REGION'] ?? 'us-east-1'),
            'credentials' => [
                'key' => (string) ($_ENV['S3_KEY'] ?? ''),
                'secret' => (string) ($_ENV['S3_SECRET'] ?? ''),
            ],
        ];
        $endpoint = trim((string) ($_ENV['S3_ENDPOINT'] ?? ''));
        if ($endpoint !== '') {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = filter_var($_ENV['S3_PATH_STYLE'] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        /** @var object $client */
        $client = new $s3Client($config);

        return new self($client, $bucket, $scanner);
    }

    public function writeStream(string $key, $stream, string $contentType = 'application/octet-stream'): void
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Storage input must be a stream.');
        }
        $this->scanner->assertClean($stream, $key);
        rewind($stream);
        $this->call('putObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $stream,
            'ContentType' => $contentType,
            'ACL' => 'private',
            'ServerSideEncryption' => $_ENV['S3_SSE'] ?? 'AES256',
        ]);
    }

    public function readStream(string $key)
    {
        $result = $this->call('getObject', ['Bucket' => $this->bucket, 'Key' => $key]);
        $body = is_array($result) ? ($result['Body'] ?? null) : null;
        if (is_object($body) && method_exists($body, 'detach')) {
            $stream = $body->detach();
            if (is_resource($stream)) {
                return $stream;
            }
        }
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to buffer S3 object.');
        }
        fwrite($stream, (string) $body);
        rewind($stream);

        return $stream;
    }

    public function exists(string $key): bool
    {
        return (bool) $this->call('doesObjectExistV2', $this->bucket, $key);
    }

    public function delete(string $key): void
    {
        $this->call('deleteObject', ['Bucket' => $this->bucket, 'Key' => $key]);
    }

    public function temporaryDownloadUrl(string $key, \DateTimeImmutable $expiresAt): ?string
    {
        $command = $this->call('getCommand', 'GetObject', ['Bucket' => $this->bucket, 'Key' => $key]);
        $request = $this->call('createPresignedRequest', $command, $expiresAt);

        return (string) $request;
    }

    public function isReady(): bool
    {
        try {
            $this->call('headBucket', ['Bucket' => $this->bucket]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function call(string $method, mixed ...$arguments): mixed
    {
        if (!method_exists($this->client, $method)) {
            throw new \RuntimeException('S3 client is missing method ' . $method . '.');
        }

        return $this->client->{$method}(...$arguments);
    }
}
