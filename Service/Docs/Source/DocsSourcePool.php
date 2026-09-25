<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Docs\Source;

use MagoAssistant\Mago\Api\Config\RepositoryInterface as ConfigRepository;
use MagoAssistant\Mago\Api\Docs\DocsSourceInterface;

class DocsSourcePool
{
    /** @var array<string, DocsSourceInterface> Keyed by source code */
    private array $sources = [];

    /**
     * @param DocsSourceInterface[] $sources
     */
    public function __construct(
        private readonly ConfigRepository $config,
        array $sources = []
    ) {
        foreach ($sources as $source) {
            $this->sources[$source->getCode()] = $source;
        }
    }

    /**
     * @return DocsSourceInterface[]
     */
    public function getAll(): array
    {
        return array_values($this->sources);
    }

    public function get(string $code): ?DocsSourceInterface
    {
        return $this->sources[$code] ?? null;
    }

    /**
     * The source selected in the admin "Source" setting (mago/docs/source).
     *
     * @throws \RuntimeException when the configured provider is not registered
     */
    public function getActive(): DocsSourceInterface
    {
        $code = $this->config->getDocsSourceCode();
        $source = $this->get($code);
        if ($source === null) {
            throw new \RuntimeException('Unknown docs source: ' . $code);
        }

        return $source;
    }
}
