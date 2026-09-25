<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MageOS\AiBase\Api\AiClientInterface;
use MagoAssistant\Mago\Service\Ai\Client;

/**
 * Provider client that records every conversation it is sent and answers from a queue of canned
 * turns, falling back to a plain "done"
 */
final class FakeChatClient extends Client
{
    /** @var list<array<int, array<string, mixed>>> */
    private array $requests = [];

    /** @var list<array<string, mixed>> */
    private array $turns = [];

    public function __construct()
    {
    }

    /**
     * @param array<string, mixed> $turn
     */
    public function withTurn(array $turn): self
    {
        $this->turns[] = $turn;

        return $this;
    }

    /**
     * @return list<array<int, array<string, mixed>>>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    public function resolve(): AiClientInterface
    {
        return new FakeAiClient();
    }

    public function chat(AiClientInterface $client, array $messages, array $tools): array
    {
        return $this->answer($messages);
    }

    public function stream(AiClientInterface $client, array $messages, array $tools, callable $onChunk): array
    {
        return $this->answer($messages);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    private function answer(array $messages): array
    {
        $this->requests[] = $messages;

        return array_shift($this->turns) ?? ['content' => 'done', 'tool_calls' => []];
    }
}
