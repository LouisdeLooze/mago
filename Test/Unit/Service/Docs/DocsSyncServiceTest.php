<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Docs;

use Magento\Framework\FlagManager;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Logger\ErrorLogger;
use MagoAssistant\Mago\Model\Doc\Repository as DocRepository;
use MagoAssistant\Mago\Service\Docs\DocsSyncService;
use MagoAssistant\Mago\Service\Docs\ExlMarkdownNormalizer;
use MagoAssistant\Mago\Service\Docs\Source\DocsSourcePool;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeConfigRepository;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeDocsSource;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class DocsSyncServiceTest extends TestCase
{
    private DocRepository&Stub $docRepository;
    private FlagManager&Stub $flagManager;
    /** @var list<array<string, mixed>>|null */
    private ?array $storedRows = null;
    /** @var array<string, mixed> */
    private array $flags = [];

    protected function setUp(): void
    {
        $this->storedRows = null;
        $this->flags = [];

        $this->docRepository = $this->createStub(DocRepository::class);
        $this->docRepository->method('replaceAll')->willReturnCallback(function (array $rows): void {
            $this->storedRows = $rows;
        });

        $this->flagManager = $this->createStub(FlagManager::class);
        $this->flagManager->method('getFlagData')->willReturnCallback(fn ($code) => $this->flags[$code] ?? null);
        $this->flagManager->method('saveFlag')->willReturnCallback(function ($code, $value): bool {
            $this->flags[$code] = $value;
            return true;
        });
    }

    private function newService(FakeDocsSource $source): DocsSyncService
    {
        $config = (new FakeConfigRepository())
            ->withDocsEnabled(true)
            ->withDocsRepo('acme/docs', 'main')
            ->withDocsSourceCode($source->getCode());

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        return new DocsSyncService(
            $config,
            new DocsSourcePool($config, [$source]),
            new ExlMarkdownNormalizer(),
            $this->docRepository,
            $this->flagManager,
            new ErrorLogger(new FakeLogger(), new Json()),
            $lockManager
        );
    }

    #[Test]
    public function indexesFilesWithTheSourcesUrl(): void
    {
        $source = new FakeDocsSource('fake', 'rev1', ['help/foo.md' => "---\ntitle: Foo\n---\nHello world"]);

        $result = $this->newService($source)->sync();

        self::assertSame(['indexed' => 1, 'sha' => 'rev1'], $result);
        self::assertSame('fake://acme/docs/main/help/foo.md', $this->storedRows[0]['url']);
        self::assertSame('rev1', $this->flags['mago_docs_source_sha']);
    }

    #[Test]
    public function skipsDownloadWhenRevisionUnchanged(): void
    {
        $this->flags['mago_docs_source_sha'] = 'rev1';
        $this->docRepository->method('count')->willReturn(3);
        $source = new FakeDocsSource('fake', 'rev1', ['help/foo.md' => 'x']);

        $result = $this->newService($source)->sync();

        self::assertSame('up-to-date', $result['skipped']);
        self::assertSame(0, $source->fetchFilesCalls);
    }

    #[Test]
    public function recordsErrorWhenDownloadFails(): void
    {
        $source = new FakeDocsSource('fake', 'rev1', null);

        $result = $this->newService($source)->sync();

        self::assertStringContainsString('Could not download', $result['error']);
        self::assertNull($this->storedRows);
        self::assertArrayHasKey('mago_docs_last_error', $this->flags);
    }
}
