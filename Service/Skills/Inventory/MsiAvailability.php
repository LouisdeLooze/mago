<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Inventory;

use Magento\Framework\Module\Manager as ModuleManager;

/**
 * Whether Multi-Source Inventory is enabled with the modules the stock tools read from.
 */
class MsiAvailability
{
    private const MODULES = [
        'Magento_Inventory',
        'Magento_InventoryApi',
        'Magento_InventorySales',
        'Magento_InventorySalesApi',
    ];

    /** @var bool|null Memoized per request */
    private ?bool $enabled = null;

    public function __construct(
        private readonly ModuleManager $moduleManager
    ) {
    }

    public function isEnabled(): bool
    {
        if ($this->enabled === null) {
            $this->enabled = true;
            foreach (self::MODULES as $module) {
                if (!$this->moduleManager->isEnabled($module)) {
                    $this->enabled = false;
                    break;
                }
            }
        }

        return $this->enabled;
    }
}
