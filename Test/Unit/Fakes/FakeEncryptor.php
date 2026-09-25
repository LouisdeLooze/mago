<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use Magento\Framework\Encryption\EncryptorInterface;

final class FakeEncryptor implements EncryptorInterface
{
    private const VERSION_PREFIX = '1:3:';

    private bool $isDecryptFailing = false;

    public function givenDecryptFails(): self
    {
        $this->isDecryptFailing = true;

        return $this;
    }

    public function getHash($password, $salt = false): string
    {
        return $this->hash($password);
    }

    public function hash($data): string
    {
        return hash('sha256', (string)$data);
    }

    public function validateHash($password, $hash): bool
    {
        return $this->isValidHash($password, $hash);
    }

    public function isValidHash($password, $hash): bool
    {
        return $this->hash($password) === $hash;
    }

    public function validateHashVersion($hash, $validateCount = false): bool
    {
        return true;
    }

    public function encrypt($data): string
    {
        return self::VERSION_PREFIX . base64_encode((string)$data);
    }

    public function decrypt($data): string
    {
        if ($this->isDecryptFailing) {
            throw new \UnexpectedValueException('The fake encryptor was told to fail decryption.');
        }

        return (string)base64_decode(substr((string)$data, strlen(self::VERSION_PREFIX)), true);
    }

    public function validateKey($key): void
    {
    }
}
