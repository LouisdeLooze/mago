<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Command;

use MagoAssistant\Mago\Api\ChatServiceInterface;
use MagoAssistant\Mago\Api\Command\CommandInterface;
use MagoAssistant\Mago\Service\Tool\ToolRegistry;

/**
 * Slash command backed by one of the assistant's tools. Every subcommand becomes a tool call
 * that goes through the same permission and ACL checks as a call the AI would have made.
 */
abstract class AbstractToolCommand implements CommandInterface
{
    /** The admin typed the command, so the call counts as confirmed; one call per run needs no unique id */
    private const TOOL_CALL_ID = 'slash_command';

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ChatServiceInterface $chatService
    ) {
    }

    /**
     * Name of the tool the subcommands are executed with
     */
    abstract protected function getToolName(): string;

    public function isAvailable(?int $adminUserId, ?string $subcommand = null): bool
    {
        $tool = $this->toolRegistry->getTool($this->getToolName(), $adminUserId);
        if ($tool === null) {
            return false;
        }
        if ($subcommand === null) {
            return true;
        }
        $definition = $this->getSubcommands()[$subcommand] ?? null;
        if ($definition === null) {
            return false;
        }

        return $definition['readOnly'] || $this->toolRegistry->hasWriteAccess($tool, $adminUserId);
    }

    /**
     * Execute one tool action; denials and exceptions come back as ['error' => ...]
     *
     * @param array<string, mixed> $input
     * @param int $adminUserId
     * @param callable $onChunk
     * @return array<string, mixed>
     */
    protected function runTool(array $input, int $adminUserId, callable $onChunk): array
    {
        $toolCall = ['id' => self::TOOL_CALL_ID, 'name' => $this->getToolName(), 'input' => $input];
        $results = $this->chatService->executeConfirmedTools([$toolCall], $adminUserId, $onChunk);

        return $results[self::TOOL_CALL_ID] ?? ['error' => 'The tool returned no result'];
    }

    /**
     * No write subcommands by default; a command with any overrides this to map them to tool calls.
     *
     * @param string $subcommand
     * @param string[] $args
     * @return array<int, array{id: string, name: string, input: array<string, mixed>}>
     */
    public function getConfirmableToolCalls(string $subcommand, array $args): array
    {
        return [];
    }

    /**
     * One tool call against this command's tool. The id is stored with the conversation and paired
     * with its tool result by id alone, so it has to be unique across the whole conversation: a
     * second "/cache clean config" must not reuse the first one's id.
     *
     * @param array<string, mixed> $input
     * @return array{id: string, name: string, input: array<string, mixed>}
     */
    protected function toolCall(string $subcommand, int $index, array $input): array
    {
        return [
            'id' => sprintf('slash_%s_%d_%s', $subcommand, $index, bin2hex(random_bytes(6))),
            'name' => $this->getToolName(),
            'input' => $input,
        ];
    }

    protected function renderError(string $error): string
    {
        return '**Error:** ' . $error;
    }

    /**
     * Markdown table; cells are pipe-escaped so a value cannot break the row
     *
     * @param string[] $headers
     * @param array<int, array<int, string>> $rows
     * @return string
     */
    protected function renderTable(array $headers, array $rows): string
    {
        $escape = static fn (string $cell): string => str_replace('|', '\|', $cell);
        $lines = [
            '| ' . implode(' | ', array_map($escape, $headers)) . ' |',
            '|' . str_repeat('---|', count($headers)),
        ];
        foreach ($rows as $row) {
            $lines[] = '| ' . implode(' | ', array_map($escape, $row)) . ' |';
        }

        return implode("\n", $lines);
    }
}
