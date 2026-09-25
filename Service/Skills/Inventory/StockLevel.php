<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Inventory;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Exception\NoSuchEntityException;
use MagoAssistant\Mago\Api\Tool\AvailabilityAwareToolInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

/**
 * Stock of one product from the legacy catalog stock item. Only offered without MSI, where that
 * item is the single source of truth; with MSI it holds the default source only.
 */
class StockLevel implements AvailabilityAwareToolInterface
{
    private const BACKORDERS = [
        Stock::BACKORDERS_NO => 'no',
        Stock::BACKORDERS_YES_NONOTIFY => 'allowed',
        Stock::BACKORDERS_YES_NOTIFY => 'allowed_notify_customer',
    ];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly MsiAvailability $msiAvailability
    ) {
    }

    public function isAvailable(): bool
    {
        return !$this->msiAvailability->isEnabled();
    }

    public function getName(): string
    {
        return 'stock_level';
    }

    public function getDescription(): string
    {
        return 'Show the stock of one product by SKU from the single catalog stock item: quantity, '
            . 'in-stock status, whether stock is managed and whether backorders are allowed. Does '
            . 'not know inventory sources, warehouses or reservations, does not change stock and '
            . 'does not search products by name; the exact SKU is required.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sku' => [
                    'type' => 'string',
                    'description' => 'Exact product SKU, e.g. "24-MB01".',
                ],
            ],
            'required' => ['sku'],
        ];
    }

    public function getMagentoAcl(array $input = []): string
    {
        return 'Magento_Catalog::products';
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
        return [
            'sku' => [PiiClass::PUBLIC],
            'product_name' => [PiiClass::PUBLIC],
            'type' => [PiiClass::PUBLIC],
            'qty' => [PiiClass::PUBLIC],
            'is_in_stock' => [PiiClass::PUBLIC],
            'manage_stock' => [PiiClass::PUBLIC],
            'backorders' => [PiiClass::PUBLIC],
            'notify_stock_qty' => [PiiClass::PUBLIC],
        ];
    }

    public function getInstructions(): string
    {
        return 'When manage_stock is false the quantity is not tracked and the product is always '
            . 'orderable; say that instead of reporting the number. A configurable or bundle product '
            . 'has no quantity of its own: suggest asking for the SKU of a child product.';
    }

    public function execute(array $params): array
    {
        $sku = trim((string)($params['sku'] ?? ''));
        if ($sku === '') {
            return ['error' => 'stock_level needs a sku'];
        }

        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException) {
            return ['error' => 'No product with SKU ' . $sku];
        }

        $item = $this->stockRegistry->getStockItemBySku($product->getSku());

        return [
            'sku' => $product->getSku(),
            'product_name' => (string)$product->getName(),
            'type' => $product->getTypeId(),
            'qty' => (float)$item->getQty(),
            'is_in_stock' => (bool)$item->getIsInStock(),
            'manage_stock' => (bool)$item->getManageStock(),
            'backorders' => self::BACKORDERS[(int)$item->getBackorders()] ?? 'no',
            'notify_stock_qty' => (float)$item->getNotifyStockQty(),
        ];
    }
}
