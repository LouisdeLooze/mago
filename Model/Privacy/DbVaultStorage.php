<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Model\Privacy;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\DuplicateException;
use MagoAssistant\Mago\Api\Privacy\VaultStorageInterface;
use MagoAssistant\Mago\Logger\DebugLogger;
use MagoAssistant\Mago\Logger\ErrorLogger;

/**
 * Stores the token vault in mago_pii_token. Every DB access is guarded: before setup:upgrade has
 * created the table, or on any connection error, load returns nothing and persist is a no-op, so
 * the ConversationVault simply behaves as request-scoped memory and the chat keeps working.
 */
class DbVaultStorage implements VaultStorageInterface
{
    private const TABLE = 'mago_pii_token';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ErrorLogger $errorLogger,
        private readonly DebugLogger $debugLogger,
        private readonly VaultValueCipher $cipher
    ) {
    }

    public function loadForConversation(int $conversationId): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(self::TABLE);
            $select = $connection->select()
                ->from($table, ['token', 'value', 'token_type'])
                ->where('conversation_id = ?', $conversationId);

            $rows = [];
            foreach ($connection->fetchAll($select) as $row) {
                $rows[] = ['token' => $row['token'], 'value' => $this->cipher->decrypt((string)$row['value']), 'type' => $row['token_type']];
            }

            return $rows;
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('PII vault load', $e->getMessage());

            return [];
        }
    }

    public function persist(int $conversationId, string $token, string $value, string $type): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $connection->insert($this->resourceConnection->getTableName(self::TABLE), [
                'conversation_id' => $conversationId,
                'token' => $token,
                'value' => $this->cipher->encrypt($value),
                'token_type' => $type,
            ]);
        } catch (DuplicateException $e) {
            // Two requests in one conversation (a stream still running beside a confirm, or a double
            // submit) both read the same highest number and mint the same token for different
            // values. The unique key turns that into a refused insert rather than a second row that
            // wins the next load and hands one conversation another's value. The token that lost
            // simply does not persist, and reads back as an unresolved one.
            $this->debugLogger->addLog('PII vault persist skipped a duplicate token', [
                'conversation_id' => $conversationId,
                'token' => $token,
            ]);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('PII vault persist', $e->getMessage());
        }
    }
}
