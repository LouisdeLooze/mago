<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MageOS\AiBase\Api\AiClientInterface;
use MageOS\AiBase\Api\Data\ChatRequestInterface;
use MageOS\AiBase\Api\Data\ChatResponseInterface;

/**
 * Resolved provider handle for FakeChatClient, which answers every turn itself; calling the
 * provider directly is a test bug
 */
final class FakeAiClient implements AiClientInterface
{
    public function chat(ChatRequestInterface $request, array $options = []): ChatResponseInterface
    {
        throw new \LogicException('FakeAiClient is never called directly; FakeChatClient answers the turn.');
    }

    public function streamChat(ChatRequestInterface $request, array $options = []): \Generator
    {
        throw new \LogicException('FakeAiClient is never called directly; FakeChatClient answers the turn.');
    }

    public function complete(string $prompt, array $options = []): string
    {
        throw new \LogicException('FakeAiClient is never called directly; FakeChatClient answers the turn.');
    }

    public function getServiceCode(): string
    {
        return 'fake';
    }

    public function getServiceId(): string
    {
        return 'fake';
    }

    public function getModel(): string
    {
        return 'fake-model';
    }

    public function getConsumer(): string
    {
        return 'fake';
    }
}
