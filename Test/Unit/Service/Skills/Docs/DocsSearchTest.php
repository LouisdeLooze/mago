<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Skills\Docs;

use MagoAssistant\Mago\Service\Skills\Docs\DocsSearch;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeAuthorization;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeConfigRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocsSearchTest extends TestCase
{
    private function docsSearch(FakeConfigRepository $config): DocsSearch
    {
        return new DocsSearch(new FakeAuthorization(), $config, []);
    }

    #[Test]
    public function defaultsToTheMagentoDocsDescription(): void
    {
        $description = $this->docsSearch(new FakeConfigRepository())->getDescription();

        self::assertStringContainsString('official Magento/Adobe Commerce admin documentation', $description);
    }

    #[Test]
    public function usesTheAdminDescriptionWhenEnabled(): void
    {
        $config = (new FakeConfigRepository())
            ->withDocsDescribeEnabled(true)
            ->withDocsDescription('our internal warehouse and fulfilment runbooks');

        $description = $this->docsSearch($config)->getDescription();

        self::assertStringContainsString('our internal warehouse and fulfilment runbooks', $description);
        self::assertStringNotContainsString('official Magento/Adobe Commerce admin documentation', $description);
    }

    #[Test]
    public function ignoresAnEmptyDescriptionEvenWhenEnabled(): void
    {
        $config = (new FakeConfigRepository())
            ->withDocsDescribeEnabled(true)
            ->withDocsDescription('');

        $description = $this->docsSearch($config)->getDescription();

        self::assertStringContainsString('official Magento/Adobe Commerce admin documentation', $description);
    }
}
