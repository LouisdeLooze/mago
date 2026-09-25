<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Api\Docs;

/**
 * A place the docs sync can pull a repository from: a Git host plus how it authenticates.
 * Each implementation is one option of the admin "Source" dropdown (mago/docs/source) and is
 * registered in DocsSourcePool via di.xml.
 */
interface DocsSourceInterface
{
    /**
     * Value stored in mago/docs/source when this source is selected, e.g. "github_app".
     */
    public function getCode(): string;

    /**
     * Label shown in the admin "Source" dropdown.
     */
    public function getLabel(): string;

    /**
     * The revision (commit SHA) $ref currently points at — a cheap change token that lets the sync
     * skip the full download when nothing has changed. Null on failure.
     */
    public function fetchRevision(string $repo, string $ref): ?string;

    /**
     * The docs in $repo@$ref as [repo-relative path => contents], e.g. ["help/foo.md" => "..."].
     *
     * @return array<string, string>|null null on transport/decode failure (empty array = ref had no docs)
     */
    public function fetchFiles(string $repo, string $ref): ?array;

    /**
     * Browsable URL of $path in $repo@$ref, stored on each indexed doc.
     */
    public function getFileUrl(string $repo, string $ref, string $path): string;
}
