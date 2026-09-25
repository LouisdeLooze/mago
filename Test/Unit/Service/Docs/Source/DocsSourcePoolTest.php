<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Docs\Source;

use MagoAssistant\Mago\Service\Docs\Source\DocsSourcePool;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeConfigRepository;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeDocsSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocsSourcePoolTest extends TestCase
{
    #[Test]
    public function indexesSourcesByCode(): void
    {
        $gitlab = new FakeDocsSource('gitlab');
        $pool = new DocsSourcePool(new FakeConfigRepository(), [new FakeDocsSource('github_public'), $gitlab]);

        self::assertSame($gitlab, $pool->get('gitlab'));
        self::assertNull($pool->get('bitbucket'));
        self::assertCount(2, $pool->getAll());
    }

    #[Test]
    public function activeSourceFollowsConfiguredCode(): void
    {
        $gitlab = new FakeDocsSource('gitlab');
        $config = (new FakeConfigRepository())->withDocsSourceCode('gitlab');
        $pool = new DocsSourcePool($config, [new FakeDocsSource('github_public'), $gitlab]);

        self::assertSame($gitlab, $pool->getActive());
    }

    #[Test]
    public function unknownSourceCodeThrows(): void
    {
        $config = (new FakeConfigRepository())->withDocsSourceCode('bitbucket');
        $pool = new DocsSourcePool($config, [new FakeDocsSource('github_public')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown docs source: bitbucket');
        $pool->getActive();
    }
}
