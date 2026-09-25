<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Skills\Inventory;

use Magento\Framework\Module\Manager as ModuleManager;
use MagoAssistant\Mago\Service\Skills\Inventory\MsiAvailability;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MsiAvailabilityTest extends TestCase
{
    #[Test]
    public function itIsEnabledWhenAllInventoryModulesAreEnabled(): void
    {
        self::assertTrue($this->availability([])->isEnabled());
    }

    #[Test]
    public function itIsDisabledWhenOneInventoryModuleIsDisabled(): void
    {
        self::assertFalse($this->availability(['Magento_InventorySales'])->isEnabled());
    }

    #[Test]
    public function itIsDisabledWhenTheInventoryModulesAreAbsent(): void
    {
        self::assertFalse($this->availability([
            'Magento_Inventory',
            'Magento_InventoryApi',
            'Magento_InventorySales',
            'Magento_InventorySalesApi',
        ])->isEnabled());
    }

    /**
     * @param string[] $disabled
     */
    private function availability(array $disabled): MsiAvailability
    {
        $moduleManager = $this->createStub(ModuleManager::class);
        $moduleManager->method('isEnabled')->willReturnCallback(
            static fn (string $module): bool => !in_array($module, $disabled, true)
        );

        return new MsiAvailability($moduleManager);
    }
}
