<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Service\Usage\UsageLogger;

/**
 * Usage logger that counts the turns it was told about instead of writing them to the database
 */
final class FakeUsageLogger extends UsageLogger
{
    /** @var int Turns log() was called for */
    private int $loggedTurns = 0;

    public function __construct()
    {
    }

    public function log(
        int $adminUserId,
        ?int $conversationId,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        array $skillNames = [],
        ?array $requestPayload = null,
        ?array $responsePayload = null
    ): void {
        $this->loggedTurns++;
    }

    public function getLoggedTurns(): int
    {
        return $this->loggedTurns;
    }
}
