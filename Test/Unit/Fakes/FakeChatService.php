<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Api\ChatServiceInterface;

/**
 * Records the tool calls handed to executeConfirmedTools() and answers them from a callback,
 * so command tests can assert what a command asks the tool for without a provider or DI.
 */
final class FakeChatService implements ChatServiceInterface
{
    /** @var list<array<string, mixed>> Tool calls received, in order */
    public array $toolCalls = [];

    /** @var list<array{type: string, data: array<string, mixed>}> Chunks emitted through $onChunk */
    public array $chunks = [];

    /** @var callable(array<string, mixed>): array<string, mixed> */
    private $resultFor;

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $resultFor Tool call => tool result
     */
    public function __construct(callable $resultFor)
    {
        $this->resultFor = $resultFor;
    }

    public function processMessage(array $messages, ?int $conversationId = null, ?int $adminUserId = null): array
    {
        return ['content' => '', 'tool_calls' => []];
    }

    public function processMessageStreaming(
        array $messages,
        callable $onChunk,
        ?int $conversationId = null,
        ?int $adminUserId = null
    ): array {
        return ['content' => '', 'tool_calls' => []];
    }

    public function prepareToolConfirmation(
        array $toolCalls,
        ?callable $onChunk = null,
        ?int $adminUserId = null
    ): array {
        if ($onChunk) {
            $onChunk('confirm', ['tools' => $toolCalls]);
        }

        return ['content' => '', 'tool_calls' => $toolCalls, 'pending_confirmation' => true];
    }

    public function executeConfirmedTools(
        array $toolCalls,
        ?int $adminUserId = null,
        ?callable $onChunk = null,
        ?array $selectedIds = null,
        ?int $conversationId = null
    ): array {
        $results = [];
        foreach ($toolCalls as $toolCall) {
            if ($selectedIds !== null && !in_array((string)($toolCall['id'] ?? ''), $selectedIds, true)) {
                $results[$toolCall['id']] = ['skipped' => true, 'reason' => 'The user chose not to run this action.'];
                continue;
            }

            $this->toolCalls[] = $toolCall;
            if ($onChunk) {
                $onChunk('tool_status', ['name' => $toolCall['name'], 'status' => 'running']);
                $onChunk('tool_status', ['name' => $toolCall['name'], 'status' => 'done']);
            }
            $results[$toolCall['id']] = ($this->resultFor)($toolCall);
        }

        return $results;
    }

    /**
     * Inputs of the recorded tool calls, e.g. [['action' => 'flush']]
     *
     * @return list<array<string, mixed>>
     */
    public function inputs(): array
    {
        return array_map(static fn (array $call): array => $call['input'], $this->toolCalls);
    }
}
