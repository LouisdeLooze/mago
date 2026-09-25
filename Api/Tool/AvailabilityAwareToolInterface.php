<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Api\Tool;

/**
 * Tool that only applies to some store setups, such as one that needs an optional Magento module.
 *
 * The registry drops a tool that reports itself unavailable, so the model never sees it and the
 * permission screen does not list it.
 *
 * @api
 */
interface AvailabilityAwareToolInterface extends ToolInterface
{
    /**
     * Whether the tool can run on this store
     *
     * @return bool
     */
    public function isAvailable(): bool;
}
