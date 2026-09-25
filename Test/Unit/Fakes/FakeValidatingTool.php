<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Api\Tool\ToolInterface;
use MagoAssistant\Mago\Api\Tool\ValidatingToolInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

/**
 * Write tool that knows a fixed set of ids and refuses any other one before confirmation, in the
 * shape IndexerManager and CacheManager use: an error plus a valid_* list of id/title pairs.
 */
final class FakeValidatingTool implements ToolInterface, ValidatingToolInterface
{
    /**
     * @param string[] $knownIds
     */
    public function __construct(
        private readonly string $name,
        private readonly array $knownIds
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return 'Fake validating tool';
    }

    public function getParameterSchema(): array
    {
        return ['type' => 'object', 'properties' => ['args' => ['type' => 'array']]];
    }

    public function execute(array $params): array
    {
        return ['executed' => true];
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function isReadOnlyAction(array $input): bool
    {
        return false;
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function getFieldClassification(string $action = ''): array
    {
        return [PiiClass::ANY => [PiiClass::PUBLIC]];
    }

    public function getMagentoAcl(array $input = []): string
    {
        return '';
    }

    public function findRefusal(array $input): ?array
    {
        $unknownIds = array_values(array_diff($input['args'] ?? [], $this->knownIds));
        if ($unknownIds === []) {
            return null;
        }

        return [
            'error' => sprintf('Unknown id "%s".', $unknownIds[0]),
            'valid_ids' => array_map(
                static fn (string $id): array => ['id' => $id, 'title' => ucfirst($id)],
                $this->knownIds
            ),
        ];
    }
}
