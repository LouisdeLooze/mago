<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Command;

use MagoAssistant\Mago\Service\Command\CacheCommand;
use MagoAssistant\Mago\Service\Tool\ToolRegistry;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeChatService;
use MagoAssistant\Mago\Test\Unit\Fakes\FakePermissionChecker;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CacheCommandTest extends TestCase
{
    private const ADMIN_ID = 7;

    private const STATUS = [
        'cache_types' => [
            ['id' => 'config', 'label' => 'Configuration', 'status' => 'enabled'],
            ['id' => 'full_page', 'label' => 'Page Cache', 'status' => 'disabled'],
        ],
    ];

    /** @var list<array{type: string, data: array<string, mixed>}> */
    private array $chunks = [];

    #[Test]
    public function flushAsksWhatToClearInsteadOfFlushingEverything(): void
    {
        $chat = $this->chat(static fn (array $call): array => ['success' => true]);

        $reply = $this->command($chat)->execute('flush', [], self::ADMIN_ID, $this->chunk());

        self::assertSame([], $chat->inputs(), 'a bare flush runs no tool; it asks first');
        self::assertStringContainsString('rarely what a change needs', $reply);
        self::assertStringContainsString('`full_page`', $reply);
        self::assertStringContainsString('/cache clean', $reply);
    }

    #[Test]
    public function cleanFlushesEachGivenTypeOnceAndReportsUnknownOnes(): void
    {
        $chat = $this->chat(static fn (array $call): array => $call['input']['cache_type'] === 'bogus'
            ? ['error' => 'Unknown cache type: bogus. Use "status" action to list available types.']
            : ['success' => true, 'message' => 'flushed']);

        $reply = $this->command($chat)->execute(
            'clean',
            ['config', 'bogus', 'config', 'layout'],
            self::ADMIN_ID,
            $this->chunk()
        );

        self::assertSame([
            ['action' => 'flush_type', 'cache_type' => 'config'],
            ['action' => 'flush_type', 'cache_type' => 'bogus'],
            ['action' => 'flush_type', 'cache_type' => 'layout'],
        ], $chat->inputs());
        self::assertStringContainsString('**Cleaned 2 cache types:** `config`, `layout`', $reply);
        self::assertStringContainsString('**Error:** Unknown cache type: bogus', $reply);
    }

    #[Test]
    public function cleanWithoutTypesShowsUsageAndTheAvailableTypes(): void
    {
        $chat = $this->chat(static fn (array $call): array => self::STATUS);

        $reply = $this->command($chat)->execute('clean', [], self::ADMIN_ID, $this->chunk());

        self::assertSame([['action' => 'status']], $chat->inputs());
        self::assertSame(
            "Usage: `/cache clean <type> [type...]`\n\nAvailable types: `config`, `full_page`",
            $reply
        );
    }

    #[Test]
    public function statusRendersATable(): void
    {
        $chat = $this->chat(static fn (array $call): array => self::STATUS);

        $reply = $this->command($chat)->execute('status', [], self::ADMIN_ID, $this->chunk());

        self::assertSame(
            "**Cache status**\n\n"
            . "| Type | Label | Status |\n"
            . "|---|---|---|\n"
            . "| `config` | Configuration | Enabled |\n"
            . "| `full_page` | Page Cache | Disabled |",
            $reply
        );
    }

    #[Test]
    public function toolDenialSurfacesAsAnError(): void
    {
        $chat = $this->chat(static fn (array $call): array => ['error' => 'Access denied: nope']);

        $reply = $this->command($chat)->execute('clean', ['config'], self::ADMIN_ID, $this->chunk());

        self::assertStringContainsString('**Error:** Access denied: nope', $reply);
    }

    #[Test]
    public function availabilityFollowsTheToolGrant(): void
    {
        $chat = $this->chat(static fn (array $call): array => []);

        $readOnly = $this->command($chat, 'read');
        self::assertTrue($readOnly->isAvailable(self::ADMIN_ID));
        self::assertTrue($readOnly->isAvailable(self::ADMIN_ID, 'status'));
        self::assertFalse($readOnly->isAvailable(self::ADMIN_ID, 'flush'));
        self::assertFalse($readOnly->isAvailable(self::ADMIN_ID, 'unknown'));

        self::assertTrue($this->command($chat, 'write')->isAvailable(self::ADMIN_ID, 'flush'));
        self::assertFalse($this->command($chat, 'disabled')->isAvailable(self::ADMIN_ID));
    }

    #[Test]
    public function confirmableToolCallsMapWritesAndLeaveStatusEmpty(): void
    {
        $command = $this->command($this->chat(static fn (array $call): array => []));

        // flush ("everything") asks first, so it maps to no card; only the scoped clean does.
        self::assertSame([], $command->getConfirmableToolCalls('flush', []));

        $calls = $command->getConfirmableToolCalls('clean', ['config', 'full_page', 'config']);
        self::assertSame(
            [
                ['name' => 'cache_manager', 'input' => ['action' => 'flush_type', 'cache_type' => 'config']],
                ['name' => 'cache_manager', 'input' => ['action' => 'flush_type', 'cache_type' => 'full_page']],
            ],
            array_map(static fn (array $call): array => array_diff_key($call, ['id' => true]), $calls)
        );
        self::assertMatchesRegularExpression('/^slash_clean_0_[0-9a-f]{12}$/', $calls[0]['id']);
        self::assertMatchesRegularExpression('/^slash_clean_1_[0-9a-f]{12}$/', $calls[1]['id']);

        self::assertSame([], $command->getConfirmableToolCalls('status', []));
        self::assertSame([], $command->getConfirmableToolCalls('clean', []));
    }

    #[Test]
    public function confirmableToolCallsGetANewIdEveryTimeTheSameCleanIsTyped(): void
    {
        $command = $this->command($this->chat(static fn (array $call): array => []));

        $first = $command->getConfirmableToolCalls('clean', ['config']);
        $second = $command->getConfirmableToolCalls('clean', ['config']);

        self::assertNotSame($first[0]['id'], $second[0]['id']);
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $resultFor
     */
    private function chat(callable $resultFor): FakeChatService
    {
        return new FakeChatService($resultFor);
    }

    private function command(FakeChatService $chat, string $grant = 'write'): CacheCommand
    {
        $checker = (new FakePermissionChecker())
            ->withDecision('cache_manager', 'read', $grant !== 'disabled')
            ->withDecision('cache_manager', 'write', $grant === 'write');
        $registry = new ToolRegistry($checker, [
            new FakeTool('cache_manager', ['status', 'flush', 'flush_type'], ['status']),
        ]);

        return new CacheCommand($registry, $chat);
    }

    private function chunk(): callable
    {
        $this->chunks = [];

        return function (string $type, array $data): void {
            $this->chunks[] = ['type' => $type, 'data' => $data];
        };
    }
}
