<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Command;

/**
 * /cache — the chat counterpart of bin/magento cache:flush, cache:clean and cache:status
 */
class CacheCommand extends AbstractToolCommand
{
    /**
     * `/cache flush` clears every cache, which is rarely what a change needs and briefly slows the
     * whole store. Rather than run it or put a flush-all card up, the command asks what actually
     * changed and why, so the follow-up clears only the cache that covers it.
     */
    private const FLUSH_PUSHBACK = "Flushing **every** cache is rarely what a change needs, and it "
        . "briefly slows the whole store while each cache rebuilds. What are you trying to refresh, "
        . "and what changed?\n\n"
        . "- a **product** page, a **category / PLP**, a **CMS page**, or **search results** → that is "
        . "the `full_page` cache\n"
        . "- **layout or block** output → `layout`, `block_html`\n"
        . "- a **configuration** change → `config`\n\n"
        . "Tell me which one and why, and I will clear only that. You can also target a type directly, "
        . "e.g. `/cache clean full_page` (see `/cache status` for the list). If you genuinely need to "
        . "clear everything, say so and why.";

    public function getName(): string
    {
        return 'cache';
    }

    public function getDescription(): string
    {
        return 'Flush, clean or inspect the Magento caches';
    }

    public function getSubcommands(): array
    {
        return [
            'flush' => [
                'args' => '',
                'description' => 'Flush all caches, including the cache storage',
                'readOnly' => false,
            ],
            'clean' => [
                'args' => '<type> [type...]',
                'description' => 'Clean specific cache types, e.g. `config full_page`',
                'readOnly' => false,
            ],
            'status' => [
                'args' => '',
                'description' => 'List all cache types and whether they are enabled',
                'readOnly' => true,
            ],
        ];
    }

    public function execute(string $subcommand, array $args, int $adminUserId, callable $onChunk): string
    {
        return match ($subcommand) {
            'flush' => self::FLUSH_PUSHBACK,
            'clean' => $this->clean($args, $adminUserId, $onChunk),
            'status' => $this->status($adminUserId, $onChunk),
            default => $this->renderError('Unknown subcommand: ' . $subcommand),
        };
    }

    protected function getToolName(): string
    {
        return 'cache_manager';
    }

    /**
     * clean names its own cache types, so it goes straight to the confirmation card, one flush_type
     * call per distinct type (an empty clean is a usage prompt, so no call and it runs directly).
     * flush ("everything") deliberately maps to no call: it is answered with a question first (see
     * FLUSH_PUSHBACK), not a flush-all card. status is read-only.
     */
    public function getConfirmableToolCalls(string $subcommand, array $args): array
    {
        if ($subcommand !== 'clean') {
            return [];
        }

        $calls = [];
        foreach (array_values(array_unique($args)) as $index => $type) {
            $calls[] = $this->toolCall($subcommand, $index, ['action' => 'flush_type', 'cache_type' => $type]);
        }

        return $calls;
    }


    /**
     * Clean the given cache types one by one, so one unknown type does not stop the rest
     *
     * @param string[] $types
     * @param int $adminUserId
     * @param callable $onChunk
     * @return string
     */
    private function clean(array $types, int $adminUserId, callable $onChunk): string
    {
        if ($types === []) {
            return $this->renderCleanUsage($adminUserId, $onChunk);
        }

        $cleaned = [];
        $errors = [];
        foreach (array_unique($types) as $type) {
            $result = $this->runTool(['action' => 'flush_type', 'cache_type' => $type], $adminUserId, $onChunk);
            if (isset($result['error'])) {
                $errors[] = (string)$result['error'];
                continue;
            }
            $cleaned[] = '`' . $type . '`';
        }

        $lines = [];
        if ($cleaned !== []) {
            $lines[] = sprintf(
                '**Cleaned %d cache type%s:** %s',
                count($cleaned),
                count($cleaned) === 1 ? '' : 's',
                implode(', ', $cleaned)
            );
        }
        foreach ($errors as $error) {
            $lines[] = $this->renderError($error);
        }

        return implode("\n\n", $lines);
    }

    private function renderCleanUsage(int $adminUserId, callable $onChunk): string
    {
        $reply = 'Usage: `/cache clean <type> [type...]`';
        $result = $this->runTool(['action' => 'status'], $adminUserId, $onChunk);
        $types = $result['cache_types'] ?? null;
        if (!is_array($types) || $types === []) {
            return $reply;
        }

        $ids = array_map(static fn (array $type): string => '`' . (string)($type['id'] ?? '') . '`', $types);

        return $reply . "\n\nAvailable types: " . implode(', ', $ids);
    }

    private function status(int $adminUserId, callable $onChunk): string
    {
        $result = $this->runTool(['action' => 'status'], $adminUserId, $onChunk);
        if (isset($result['error'])) {
            return $this->renderError((string)$result['error']);
        }

        $rows = [];
        foreach ((array)($result['cache_types'] ?? []) as $type) {
            $rows[] = [
                '`' . (string)($type['id'] ?? '') . '`',
                (string)($type['label'] ?? ''),
                ($type['status'] ?? '') === 'enabled' ? 'Enabled' : 'Disabled',
            ];
        }
        if ($rows === []) {
            return 'No cache types found.';
        }

        return "**Cache status**\n\n" . $this->renderTable(['Type', 'Label', 'Status'], $rows);
    }
}
