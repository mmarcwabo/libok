<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Services;

use Libok\Infrastructure\Services\PasswordService;
use PHPUnit\Framework\TestCase;

final class PasswordServiceTest extends TestCase
{
    public function testHashesAndVerifiesAValidPassword(): void
    {
        $passwords = new PasswordService();
        $hash = $passwords->hash('password123');

        self::assertNotSame('password123', $hash);
        self::assertTrue($passwords->verify('password123', $hash));
        self::assertFalse($passwords->verify('wrong-password', $hash));
    }

    public function testRejectsShortPasswords(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PasswordService())->validate('short');
    }
}
