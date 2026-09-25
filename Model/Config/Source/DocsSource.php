<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MagoAssistant\Mago\Service\Docs\Source\DocsSourcePool;

/**
 * "Source" options: one per docs source registered in DocsSourcePool.
 */
class DocsSource implements OptionSourceInterface
{
    public function __construct(
        private readonly DocsSourcePool $sources
    ) {
    }

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->sources->getAll() as $source) {
            $options[] = ['value' => $source->getCode(), 'label' => __($source->getLabel())];
        }

        return $options;
    }
}
