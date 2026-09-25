<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Api;

/**
 * Chat service interface — orchestrates AI + tools
 * @api
 */
interface ChatServiceInterface
{
    /**
     * Process a user message and return the AI response
     *
     * @param array $messages Conversation history
     * @param int|null $conversationId
     * @param int|null $adminUserId
     * @return array Response with 'content', 'tool_calls', 'pending_confirmation' keys
     */
    public function processMessage(array $messages, ?int $conversationId = null, ?int $adminUserId = null): array;

    /**
     * Process a user message with streaming
     *
     * @param array $messages
     * @param callable $onChunk fn(string $type, array $data)
     * @param int|null $conversationId
     * @param int|null $adminUserId
     * @return array
     */
    public function processMessageStreaming(array $messages, callable $onChunk, ?int $conversationId = null, ?int $adminUserId = null): array;

    /**
     * Execute confirmed write tool calls
     *
     * @param array $toolCalls
     * @param int|null $adminUserId
     * @param callable|null $onChunk
     * @param string[]|null $selectedIds Tool call IDs the user ticked in a bulk confirmation; null
     *        runs them all, an unticked call is answered with a "skipped" result instead of running
     * @param int|null $conversationId Binds the privacy vault so persisted tokenised arguments
     *        rehydrate on this fresh request; pass it on every confirm round-trip
     * @return array Results keyed by tool call ID
     */
    public function executeConfirmedTools(
        array $toolCalls,
        ?int $adminUserId = null,
        ?callable $onChunk = null,
        ?array $selectedIds = null,
        ?int $conversationId = null
    ): array;

    /**
     * Put a set of write tool calls to the administrator on the confirmation card and return the
     * pending result, without a model turn. The slash-command write path uses this so a typed
     * /cache flush or /index reindex is confirmed on the same card as a write the model proposed.
     *
     * @param array<int, array<string, mixed>> $toolCalls
     * @param callable|null $onChunk fn(string $type, array $data)
     * @param int|null $adminUserId
     * @return array{content: string, tool_calls: array<int, array<string, mixed>>, pending_confirmation: bool}
     */
    public function prepareToolConfirmation(
        array $toolCalls,
        ?callable $onChunk = null,
        ?int $adminUserId = null
    ): array;
}
