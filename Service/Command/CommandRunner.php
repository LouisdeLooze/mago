<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Command;

use Magento\Framework\AuthorizationInterface;
use MagoAssistant\Mago\Api\Command\CommandInterface;
use MagoAssistant\Mago\Api\Tool\ValidatingToolInterface;
use MagoAssistant\Mago\Service\Tool\ToolRegistry;

/**
 * Parses "/name subcommand [args]" chat messages and dispatches them to the registered command
 */
class CommandRunner
{
    public const HELP = 'help';

    public const WRITE_ACL = 'MagoAssistant_Mago::assistant_write';

    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly AuthorizationInterface $authorization,
        private readonly ToolRegistry $toolRegistry
    ) {
    }

    /**
     * Whether the message is a registered slash command (or /help). Anything else that
     * starts with a slash is left to the assistant, so a typed "/revenue" still works as a prompt.
     */
    public function isCommand(string $message): bool
    {
        $parsed = $this->parse($message);
        if ($parsed === null) {
            return false;
        }

        return $parsed['name'] === self::HELP || $this->registry->get($parsed['name']) !== null;
    }

    /**
     * Run the command and return the Markdown reply
     *
     * @param string $message
     * @param int $adminUserId
     * @param callable $onChunk fn(string $type, array $data)
     * @return string
     */
    public function run(string $message, int $adminUserId, callable $onChunk): string
    {
        $parsed = $this->parse($message);
        if ($parsed === null) {
            return $this->renderHelp($adminUserId);
        }

        if ($parsed['name'] === self::HELP) {
            return $this->renderHelp($adminUserId);
        }

        $command = $this->registry->get($parsed['name']);
        if ($command === null) {
            return sprintf('Unknown command `/%s`. Type `/help` to see what is available.', $parsed['name']);
        }
        if (!$command->isAvailable($adminUserId)) {
            return sprintf('You do not have permission to use `/%s`.', $command->getName());
        }

        $subcommand = $parsed['subcommand'];
        $subcommands = $command->getSubcommands();
        if ($subcommand === '' || $subcommand === self::HELP || !isset($subcommands[$subcommand])) {
            return $this->renderUsage($command, $adminUserId);
        }

        if (!$subcommands[$subcommand]['readOnly'] && !$this->authorization->isAllowed(self::WRITE_ACL)) {
            return sprintf(
                '`/%s %s` changes the store and needs the "%s" permission, which your admin role does not have.',
                $command->getName(),
                $subcommand,
                self::WRITE_ACL
            );
        }
        if (!$command->isAvailable($adminUserId, $subcommand)) {
            return sprintf(
                'Your skill permissions do not allow `/%s %s`.',
                $command->getName(),
                $subcommand
            );
        }

        return $command->execute($subcommand, $parsed['args'], $adminUserId, $onChunk);
    }

    /**
     * The write tool calls a slash message would run, for the confirmation card — but only when the
     * admin is actually allowed to run them. A read subcommand, /help, an unknown command, a write
     * the session's ACL or skill grant forbids, or a write mapping to no call (a usage prompt) all
     * return []: the caller then runs the message through run(), which renders the reply or the
     * denial. So a permitted write is confirmed first and everything else keeps its current path.
     *
     * @param string $message
     * @param int $adminUserId
     * @return array<int, array{id: string, name: string, input: array<string, mixed>}>
     */
    public function confirmableToolCalls(string $message, int $adminUserId): array
    {
        $parsed = $this->parse($message);
        if ($parsed === null || $parsed['name'] === self::HELP) {
            return [];
        }

        $command = $this->registry->get($parsed['name']);
        if ($command === null || !$command->isAvailable($adminUserId)) {
            return [];
        }

        $subcommand = $parsed['subcommand'];
        $definition = $command->getSubcommands()[$subcommand] ?? null;
        if ($definition === null || $definition['readOnly']) {
            return [];
        }
        if (!$this->authorization->isAllowed(self::WRITE_ACL) || !$command->isAvailable($adminUserId, $subcommand)) {
            return [];
        }

        return $command->getConfirmableToolCalls($subcommand, $parsed['args']);
    }

    /**
     * The reply for confirmable tool calls their tool already refuses, such as an indexer or cache
     * type this store does not have, or null when every call can go to the confirmation card. The
     * same check a write the model proposes goes through before its card.
     *
     * @param array<int, array{id: string, name: string, input: array<string, mixed>}> $toolCalls
     * @param int $adminUserId
     * @return string|null
     */
    public function findRefusal(array $toolCalls, int $adminUserId): ?string
    {
        $errors = array_values(array_filter(array_map(
            fn (array $toolCall): ?string => $this->findRefusalError($toolCall, $adminUserId),
            $toolCalls
        )));

        return $errors === [] ? null : implode("\n\n", $errors);
    }

    /**
     * Split "/Name Sub arg1 arg2" into its parts; null when the message is not slash-prefixed
     *
     * @param string $message
     * @return array{name: string, subcommand: string, args: string[]}|null
     */
    public function parse(string $message): ?array
    {
        $message = trim($message);
        if ($message === '' || $message[0] !== '/') {
            return null;
        }

        $tokens = preg_split('/\s+/', trim(substr($message, 1))) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if ($tokens === []) {
            return null;
        }

        return [
            'name' => strtolower($tokens[0]),
            'subcommand' => strtolower($tokens[1] ?? ''),
            'args' => array_slice($tokens, 2),
        ];
    }

    private function renderHelp(int $adminUserId): string
    {
        $commands = $this->registry->getAvailable($adminUserId);
        if ($commands === []) {
            return 'No slash commands are available for your account.';
        }

        $lines = ['**Available commands**', '', '| Command | Description |', '|---|---|'];
        foreach ($commands as $command) {
            foreach ($this->usageRows($command, $adminUserId) as $row) {
                $lines[] = $row;
            }
        }
        $lines[] = '';
        $lines[] = 'Type `/<command>` without a subcommand to see its usage.';

        return implode("\n", $lines);
    }

    private function renderUsage(CommandInterface $command, int $adminUserId): string
    {
        $lines = [
            sprintf('**/%s** — %s', $command->getName(), $command->getDescription()),
            '',
            '| Command | Description |',
            '|---|---|',
        ];
        foreach ($this->usageRows($command, $adminUserId) as $row) {
            $lines[] = $row;
        }

        return implode("\n", $lines);
    }

    /**
     * Subcommands the admin may run: write subcommands need the write ACL of the current
     * session and every subcommand needs the command's own (skill grant) approval.
     *
     * @param CommandInterface $command
     * @param int|null $adminUserId
     * @return array<string, array{args: string, description: string, readOnly: bool}>
     */
    public function getAvailableSubcommands(CommandInterface $command, ?int $adminUserId): array
    {
        $canWrite = $this->authorization->isAllowed(self::WRITE_ACL);
        $available = [];
        foreach ($command->getSubcommands() as $name => $definition) {
            if (!$definition['readOnly'] && !$canWrite) {
                continue;
            }
            if (!$command->isAvailable($adminUserId, $name)) {
                continue;
            }
            $available[$name] = $definition;
        }

        return $available;
    }

    /**
     * One Markdown table row per subcommand the admin may run
     *
     * @param CommandInterface $command
     * @param int $adminUserId
     * @return string[]
     */
    private function usageRows(CommandInterface $command, int $adminUserId): array
    {
        $rows = [];
        foreach ($this->getAvailableSubcommands($command, $adminUserId) as $name => $definition) {
            $usage = '/' . $command->getName() . ' ' . $name;
            if ($definition['args'] !== '') {
                $usage .= ' ' . $definition['args'];
            }
            $rows[] = sprintf('| `%s` | %s |', $usage, $definition['description']);
        }

        return $rows;
    }

    /**
     * The error for one tool call its tool refuses, or null when the tool accepts it
     *
     * @param array $toolCall
     * @param int $adminUserId
     * @return string|null
     */
    private function findRefusalError(array $toolCall, int $adminUserId): ?string
    {
        $tool = $this->toolRegistry->getTool($toolCall['name'], $adminUserId);
        if (!$tool instanceof ValidatingToolInterface) {
            return null;
        }
        $refusal = $tool->findRefusal($toolCall['input']);
        if ($refusal === null) {
            return null;
        }

        return '**Error:** ' . (string)($refusal['error'] ?? 'The tool refused this call.')
            . $this->renderValidIds($refusal);
    }

    /**
     * The ids a refusal offers instead, from its valid_* list of ['id' => ..., 'title' => ...] pairs
     *
     * @param array<string, mixed> $refusal
     * @return string
     */
    private function renderValidIds(array $refusal): string
    {
        $validIds = array_merge(...array_values(array_map(
            static fn (array $pairs): array => array_column($pairs, 'id'),
            array_filter($refusal, $this->isValidIdList(...), ARRAY_FILTER_USE_BOTH)
        )));

        return $validIds === [] ? '' : "\n\nValid IDs: `" . implode('`, `', $validIds) . '`';
    }

    /**
     * Whether a refusal entry is one of its valid_* lists of alternatives
     *
     * @param mixed $value
     * @param string|int $key
     * @return bool
     */
    private function isValidIdList(mixed $value, string|int $key): bool
    {
        return is_array($value) && str_starts_with((string)$key, 'valid_');
    }
}
