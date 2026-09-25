<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Api\Command\CommandInterface;

/**
 * Command with one read and one write subcommand that echoes what it was called with. The write
 * maps to a tool call only when it names a target, like a real "/cache clean" without a type.
 */
final class FakeCommand implements CommandInterface
{
    /** @var list<array{subcommand: string, args: string[], adminUserId: int}> */
    public array $executions = [];

    public function __construct(
        private readonly string $name,
        private readonly bool $available = true,
        private readonly bool $writeAllowed = true
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return 'Fake ' . $this->name . ' command';
    }

    public function getSubcommands(): array
    {
        return [
            'show' => ['args' => '', 'description' => 'Show something', 'readOnly' => true],
            'apply' => ['args' => '<target>', 'description' => 'Change something', 'readOnly' => false],
        ];
    }

    public function isAvailable(?int $adminUserId, ?string $subcommand = null): bool
    {
        if (!$this->available) {
            return false;
        }
        if ($subcommand === 'apply') {
            return $this->writeAllowed;
        }

        return true;
    }

    public function execute(string $subcommand, array $args, int $adminUserId, callable $onChunk): string
    {
        $this->executions[] = ['subcommand' => $subcommand, 'args' => $args, 'adminUserId' => $adminUserId];

        return 'ran ' . $subcommand . ' ' . implode(',', $args);
    }

    public function getConfirmableToolCalls(string $subcommand, array $args): array
    {
        if ($subcommand !== 'apply' || $args === []) {
            return [];
        }

        return [['id' => 'slash_apply_0', 'name' => 'fake_tool', 'input' => ['args' => $args]]];
    }
}
