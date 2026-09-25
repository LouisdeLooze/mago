<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Model\Privacy;

use MagoAssistant\Mago\Model\Privacy\VaultValueCipher;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeEncryptor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VaultValueCipherTest extends TestCase
{
    #[Test]
    public function itStoresAValueEncryptedRatherThanAsPlaintext(): void
    {
        $cipher = new VaultValueCipher(new FakeEncryptor());

        $stored = $cipher->encrypt('jane@example.com');

        self::assertStringNotContainsString('jane@example.com', $stored);
        self::assertMatchesRegularExpression('/^\d+:\d+:/', $stored);
    }

    #[Test]
    public function itDecryptsAValueItEncrypted(): void
    {
        $cipher = new VaultValueCipher(new FakeEncryptor());
        $stored = $cipher->encrypt('jane@example.com');

        $value = $cipher->decrypt($stored);

        self::assertSame('jane@example.com', $value);
    }

    #[Test]
    public function itReturnsALegacyPlaintextRowUnchanged(): void
    {
        $cipher = new VaultValueCipher(new FakeEncryptor());

        $value = $cipher->decrypt('jane@example.com');

        self::assertSame('jane@example.com', $value);
    }

    #[Test]
    public function itReturnsTheStoredValueWhenDecryptionThrows(): void
    {
        $cipher = new VaultValueCipher((new FakeEncryptor())->givenDecryptFails());

        $value = $cipher->decrypt('1:3:c29tZXRoaW5n');

        self::assertSame('1:3:c29tZXRoaW5n', $value);
    }
}
