<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Inventory;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use MagoAssistant\Mago\Api\Tool\AvailabilityAwareToolInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

/**
 * Stock of one product per MSI source and salable quantity per stock. The MSI services are
 * resolved when the tool runs rather than injected, because stores can disable or remove the
 * Inventory modules and a constructor dependency on them would then break the whole registry.
 */
class StockLevelMsi implements AvailabilityAwareToolInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ObjectManagerInterface $objectManager,
        private readonly MsiAvailability $msiAvailability
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->msiAvailability->isEnabled();
    }

    public function getName(): string
    {
        return 'stock_level_msi';
    }

    public function getDescription(): string
    {
        return 'Show the stock of one product by SKU: the physical quantity and in-stock status per '
            . 'inventory source, and the salable quantity per stock (physical quantity minus '
            . 'reservations for orders not yet shipped). Does not change stock and does not search '
            . 'products by name; the exact SKU is required.';
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
            'source_code' => [PiiClass::PUBLIC],
            'source_name' => [PiiClass::PUBLIC],
            'quantity' => [PiiClass::PUBLIC],
            'in_stock' => [PiiClass::PUBLIC],
            'stock_id' => [PiiClass::PUBLIC],
            'stock_name' => [PiiClass::PUBLIC],
            'salable_qty' => [PiiClass::PUBLIC],
            'is_salable' => [PiiClass::PUBLIC],
            'note' => [PiiClass::PUBLIC],
        ];
    }

    public function getInstructions(): string
    {
        return 'Present sources and stocks as short tables. Salable quantity can be lower than the '
            . 'physical quantity because of open order reservations; say so when they differ. A '
            . 'product without source rows, such as a configurable or bundle, has no stock of its '
            . 'own: suggest asking for the SKU of a child product.';
    }

    public function execute(array $params): array
    {
        $sku = trim((string)($params['sku'] ?? ''));
        if ($sku === '') {
            return ['error' => 'stock_level_msi needs a sku'];
        }
        if (!$this->isAvailable()) {
            return ['error' => 'Multi-Source Inventory is not enabled on this store'];
        }

        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException) {
            return ['error' => 'No product with SKU ' . $sku];
        }

        $sources = $this->sources($product->getSku());

        return [
            'sku' => $product->getSku(),
            'product_name' => (string)$product->getName(),
            'type' => $product->getTypeId(),
            'sources' => $sources,
            'stocks' => $sources === [] ? [] : $this->stocks($product->getSku()),
        ];
    }

    /**
     * Physical quantity and status per source
     *
     * @param string $sku
     * @return array<int, array<string, mixed>>
     */
    private function sources(string $sku): array
    {
        $rows = [];
        /** @var GetSourceItemsBySkuInterface $getSourceItemsBySku */
        $getSourceItemsBySku = $this->objectManager->get(GetSourceItemsBySkuInterface::class);
        foreach ($getSourceItemsBySku->execute($sku) as $item) {
            $sourceCode = (string)$item->getSourceCode();
            $rows[] = [
                'source_code' => $sourceCode,
                'source_name' => $this->sourceName($sourceCode),
                'quantity' => (float)$item->getQuantity(),
                'in_stock' => (bool)$item->getStatus(),
            ];
        }

        return $rows;
    }

    /**
     * Salable quantity and status per stock
     *
     * @param string $sku
     * @return array<int, array<string, mixed>>
     */
    private function stocks(string $sku): array
    {
        /** @var StockRepositoryInterface $stockRepository */
        $stockRepository = $this->objectManager->get(StockRepositoryInterface::class);
        /** @var GetProductSalableQtyInterface $getProductSalableQty */
        $getProductSalableQty = $this->objectManager->get(GetProductSalableQtyInterface::class);
        /** @var IsProductSalableInterface $isProductSalable */
        $isProductSalable = $this->objectManager->get(IsProductSalableInterface::class);

        $rows = [];
        foreach ($stockRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $stock) {
            $stockId = (int)$stock->getStockId();
            $row = [
                'stock_id' => $stockId,
                'stock_name' => (string)$stock->getName(),
            ];

            try {
                $row['salable_qty'] = $getProductSalableQty->execute($sku, $stockId);
                $row['is_salable'] = $isProductSalable->execute($sku, $stockId);
            } catch (\Throwable $e) {
                // Thrown for products without source item support and for stocks the SKU is not in.
                $row['note'] = $e->getMessage();
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function sourceName(string $sourceCode): string
    {
        /** @var SourceRepositoryInterface $sourceRepository */
        $sourceRepository = $this->objectManager->get(SourceRepositoryInterface::class);
        try {
            return (string)$sourceRepository->get($sourceCode)->getName();
        } catch (NoSuchEntityException) {
            return $sourceCode;
        }
    }
}
