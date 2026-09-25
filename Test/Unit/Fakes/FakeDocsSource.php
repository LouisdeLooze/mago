<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Api\Docs\DocsSourceInterface;

/**
 * In-memory docs source: returns a canned revision and file set, and counts fetchFiles() calls.
 */
final class FakeDocsSource implements DocsSourceInterface
{
    public int $fetchFilesCalls = 0;

    /**
     * @param array<string, string>|null $files
     */
    public function __construct(
        private readonly string $code = 'fake',
        private readonly ?string $revision = 'rev1',
        private readonly ?array $files = []
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return 'Fake ' . $this->code;
    }

    public function fetchRevision(string $repo, string $ref): ?string
    {
        return $this->revision;
    }

    public function fetchFiles(string $repo, string $ref): ?array
    {
        $this->fetchFilesCalls++;

        return $this->files;
    }

    public function getFileUrl(string $repo, string $ref, string $path): string
    {
        return 'fake://' . $repo . '/' . $ref . '/' . $path;
    }
}
