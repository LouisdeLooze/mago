<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Skills\Configuration;

use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\DataObject;
use MagoAssistant\Mago\Service\Skills\Configuration\CacheManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CacheManagerTest extends TestCase
{
    #[Test]
    public function flushTypeSchemaOffersTheLiveCacheTypesAsAnEnum(): void
    {
        $schema = $this->manager(['config' => 'Configuration', 'full_page' => 'Page Cache'])
            ->getParameterSchemaForActions(['flush_type']);

        $cacheType = $schema['properties']['cache_type'];
        self::assertSame(['config', 'full_page'], $cacheType['enum']);
        self::assertStringContainsString('config (Configuration)', $cacheType['description']);
        self::assertStringContainsString('full_page (Page Cache)', $cacheType['description']);
    }

    #[Test]
    public function findRefusalPassesAKnownTypeAndRefusesAnInventedOne(): void
    {
        $manager = $this->manager(['config' => 'Configuration', 'full_page' => 'Page Cache']);

        self::assertNull($manager->findRefusal(['action' => 'flush_type', 'cache_type' => 'full_page']));

        $refusal = $manager->findRefusal(['action' => 'flush_type', 'cache_type' => 'full_page_cache']);
        self::assertIsArray($refusal);
        self::assertStringContainsString('Unknown cache type "full_page_cache"', $refusal['error']);
        self::assertSame(
            [['id' => 'config', 'label' => 'Configuration'], ['id' => 'full_page', 'label' => 'Page Cache']],
            $refusal['valid_cache_types']
        );
    }

    #[Test]
    public function findRefusalIgnoresReadsFlushAllAndAnEmptyType(): void
    {
        $manager = $this->manager(['config' => 'Configuration']);

        self::assertNull($manager->findRefusal(['action' => 'status']));
        self::assertNull($manager->findRefusal(['action' => 'flush']));
        self::assertNull($manager->findRefusal(['action' => 'flush_type', 'cache_type' => '']));
    }

    #[Test]
    public function aRunawayCacheTypeIsTruncatedInTheRefusalAndTheExecuteError(): void
    {
        $manager = $this->manager(['config' => 'Configuration']);
        $huge = str_repeat('x', 5000);

        $refusal = $manager->findRefusal(['action' => 'flush_type', 'cache_type' => $huge]);
        self::assertIsArray($refusal);
        self::assertStringContainsString(str_repeat('x', 100) . '…', $refusal['error']);
        self::assertLessThan(200, mb_strlen($refusal['error']));

        $result = $manager->execute(['action' => 'flush_type', 'cache_type' => $huge]);
        self::assertStringContainsString(str_repeat('x', 100) . '…', $result['error']);
        self::assertStringNotContainsString(str_repeat('x', 101), $result['error']);
    }

    #[Test]
    public function itFallsBackToAFreeStringCacheTypeWhenTheTypesCannotBeRead(): void
    {
        $typeList = $this->createStub(TypeListInterface::class);
        $typeList->method('getTypes')->willThrowException(new \RuntimeException('cache config unreadable'));
        $manager = new CacheManager($typeList, $this->createStub(CacheFrontendPool::class));

        $schema = $manager->getParameterSchemaForActions(['flush_type']);

        $cacheType = $schema['properties']['cache_type'];
        self::assertSame('string', $cacheType['type']);
        self::assertArrayNotHasKey('enum', $cacheType);
        self::assertStringContainsString('Use one of these exact IDs. Do not invent an ID', $cacheType['description']);
    }

    /**
     * @param array<string, string> $types id => label
     */
    private function manager(array $types): CacheManager
    {
        $typeList = $this->createStub(TypeListInterface::class);
        $typeObjects = [];
        foreach ($types as $id => $label) {
            $typeObjects[$id] = new DataObject(['id' => $id, 'cache_type' => $label, 'status' => 1]);
        }
        $typeList->method('getTypes')->willReturn($typeObjects);

        return new CacheManager($typeList, $this->createStub(CacheFrontendPool::class));
    }
}
