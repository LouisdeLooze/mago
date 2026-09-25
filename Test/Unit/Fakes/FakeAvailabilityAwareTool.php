<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Api\Tool\AvailabilityAwareToolInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

/**
 * Read-only tool whose availability is fixed at construction.
 */
final class FakeAvailabilityAwareTool implements AvailabilityAwareToolInterface
{
    public function __construct(
        private readonly string $name,
        private readonly bool $available
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return 'Fake tool ' . $this->name;
    }

    public function getParameterSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function getMagentoAcl(array $input = []): string
    {
        return '';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function isReadOnlyAction(array $input): bool
    {
        return true;
    }

    public function getFieldClassification(string $action = ''): array
    {
        return [PiiClass::ANY => [PiiClass::PUBLIC]];
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function execute(array $params): array
    {
        return [];
    }
}
