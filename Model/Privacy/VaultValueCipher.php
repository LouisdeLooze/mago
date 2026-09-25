<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Model\Privacy;

use Magento\Framework\Encryption\EncryptorInterface;

final class VaultValueCipher
{
    private const ENCRYPTED_VALUE_PREFIX_PATTERN = '/^\d+:\d+:/';

    public function __construct(
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function encrypt(string $value): string
    {
        return $this->encryptor->encrypt($value);
    }

    public function decrypt(string $stored): string
    {
        if (!$this->isEncryptedValue($stored)) {
            return $stored;
        }

        try {
            return $this->encryptor->decrypt($stored);
        } catch (\Throwable) {
            return $stored;
        }
    }

    private function isEncryptedValue(string $stored): bool
    {
        return preg_match(self::ENCRYPTED_VALUE_PREFIX_PATTERN, $stored) === 1;
    }
}
