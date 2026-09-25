<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Docs\Source;

use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Logger\ErrorLogger;
use MagoAssistant\Mago\Service\Docs\Source\TarballExtractor;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TarballExtractorTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
    }

    private function extractor(): TarballExtractor
    {
        return new TarballExtractor(new ErrorLogger(new FakeLogger(), new Json()));
    }

    #[Test]
    public function extractsOnlyHelpMarkdownUnderTheTopLevelDirectory(): void
    {
        $path = $this->writeTarGz('acme-private-docs-d34db33', [
            'help/a.md' => '# A',
            'help/_includes/inc.md' => 'partial',
            'help/logo.png' => 'binary-bytes',
            'README.md' => 'not under help',
        ]);

        $files = $this->extractor()->extract($path);

        self::assertSame([
            'help/a.md' => '# A',
            'help/_includes/inc.md' => 'partial',
        ], $files);
    }

    #[Test]
    public function keepsFileContentsExactlyEvenAcrossBlockBoundaries(): void
    {
        // 1200 bytes spans three 512-byte tar blocks, catching off-by-one padding bugs.
        $content = str_repeat('x', 1200);
        $path = $this->writeTarGz('repo-abc', ['help/big.md' => $content]);

        $files = $this->extractor()->extract($path);

        self::assertSame($content, $files['help/big.md']);
        self::assertSame(1200, strlen($files['help/big.md']));
    }

    #[Test]
    public function returnsEmptyWhenRefHasNoDocs(): void
    {
        $path = $this->writeTarGz('repo-abc', ['README.md' => 'nothing here']);

        self::assertSame([], $this->extractor()->extract($path));
    }

    #[Test]
    public function returnsNullWhenFileIsNotGzip(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mago_test_');
        $this->tmpFiles[] = $path;
        file_put_contents($path, 'this is not a gzip archive');

        self::assertNull($this->extractor()->extract($path));
    }

    /**
     * @param array<string, string> $files repo-relative path => contents
     */
    private function writeTarGz(string $topDir, array $files): string
    {
        $tar = '';
        foreach ($files as $path => $content) {
            $tar .= $this->tarEntry($topDir . '/' . $path, $content);
        }
        $tar .= str_repeat("\0", 1024); // two zero blocks terminate the archive

        $file = tempnam(sys_get_temp_dir(), 'mago_test_');
        $this->tmpFiles[] = $file;
        file_put_contents($file, gzencode($tar));

        return $file;
    }

    private function tarEntry(string $name, string $content): string
    {
        $size = strlen($content);
        $header = pack('a100', $name)
            . pack('a8', '0000644')
            . pack('a8', '0000000')
            . pack('a8', '0000000')
            . pack('a12', sprintf('%011o', $size))
            . pack('a12', sprintf('%011o', 0))
            . str_repeat(' ', 8) // checksum placeholder
            . '0'                 // typeflag: regular file
            . pack('a100', '')
            . pack('a6', 'ustar')
            . pack('a2', '00')
            . pack('a32', '')
            . pack('a32', '')
            . pack('a8', '')
            . pack('a8', '')
            . pack('a155', '');
        $header = str_pad($header, 512, "\0");

        $checksum = 0;
        for ($i = 0; $i < 512; $i++) {
            $checksum += ord($header[$i]);
        }
        $header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);

        $padded = $content . str_repeat("\0", (512 - ($size % 512)) % 512);

        return $header . $padded;
    }
}
