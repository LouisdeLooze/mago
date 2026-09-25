<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Docs;

use Magento\Framework\FlagManager;
use Magento\Framework\Lock\LockManagerInterface;
use MagoAssistant\Mago\Api\Config\RepositoryInterface as ConfigRepository;
use MagoAssistant\Mago\Logger\ErrorLogger;
use MagoAssistant\Mago\Model\Doc\Repository as DocRepository;
use MagoAssistant\Mago\Service\Docs\Source\DocsSourcePool;

class DocsSyncService
{
    private const FLAG_SHA = 'mago_docs_source_sha';
    private const FLAG_SYNCED_AT = 'mago_docs_synced_at';
    private const FLAG_ERROR = 'mago_docs_last_error';
    private const LOCK_NAME = 'mago_docs_sync';
    private const VARCHAR_MAX = 512;

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly DocsSourcePool $sources,
        private readonly ExlMarkdownNormalizer $normalizer,
        private readonly DocRepository $docRepository,
        private readonly FlagManager $flagManager,
        private readonly ErrorLogger $errorLogger,
        private readonly LockManagerInterface $lockManager
    ) {
    }

    /**
     * @param callable(string $step, int $current, int $total): void|null $onProgress
     * @return array<string, mixed> summary of what happened
     */
    public function sync(bool $force = false, ?callable $onProgress = null): array
    {
        $progress = $onProgress ?? static function (): void {};

        if (!$this->config->isDocsEnabled()) {
            return ['skipped' => 'disabled'];
        }

        // Concurrent replaceAll() writers (cron + CLI) can deadlock on the fulltext index;
        // the loser skips instead of waiting.
        try {
            $locked = $this->lockManager->lock(self::LOCK_NAME, 0);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('DocsSync', $e->getMessage());
            return ['error' => $e->getMessage()];
        }
        if (!$locked) {
            return ['skipped' => 'another sync is already running'];
        }

        try {
            return $this->doSync($force, $progress);
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * @param callable(string $step, int $current, int $total): void $progress
     * @return array<string, mixed>
     */
    private function doSync(bool $force, callable $progress): array
    {
        $repo = $this->config->getDocsSourceRepo();
        $ref = $this->config->getDocsRef();

        try {
            $source = $this->sources->getActive();

            $progress('sha', 0, 0);
            $sha = $source->fetchRevision($repo, $ref);
            if ($sha === null || $sha === '') {
                throw new \RuntimeException(
                    'Could not read ' . $repo . '@' . $ref . ' from ' . $source->getLabel()
                );
            }

            $storedShaFlag = $this->flagManager->getFlagData(self::FLAG_SHA);
            $storedSha = is_string($storedShaFlag) ? $storedShaFlag : '';
            $count = $this->docRepository->count();

            if (!$force && $sha === $storedSha && $count > 0) {
                return ['skipped' => 'up-to-date', 'sha' => $sha, 'docs' => $count];
            }

            // The source hands back every doc in the ref at once; includes are resolved against that set.
            $progress('download', 0, 0);
            $rawByPath = $source->fetchFiles($repo, $ref);
            if ($rawByPath === null) {
                throw new \RuntimeException('Could not download docs archive from ' . $repo . '@' . $ref);
            }

            if (!$rawByPath) {
                throw new \RuntimeException('Found 0 docs in ' . $repo . '@' . $ref . ' — keeping existing corpus');
            }

            $resolver = static function (string $include) use ($rawByPath): ?string {
                $path = ltrim($include, '/');
                return $rawByPath[$path] ?? null;
            };

            $totalRaw = count($rawByPath);
            $rows = [];
            $normalized_i = 0;
            foreach ($rawByPath as $path => $raw) {
                $normalized_i++;
                $progress('normalize', $normalized_i, $totalRaw);
                if (!$this->normalizer->isIndexable($path)) {
                    continue;
                }
                $normalized = $this->normalizer->normalize($raw, $resolver);
                if ($normalized['content'] === '') {
                    continue;
                }
                $title = $normalized['title'] !== '' ? $normalized['title'] : $path;
                $rows[] = [
                    'source' => $repo,
                    'path' => mb_substr($path, 0, self::VARCHAR_MAX),
                    'title' => mb_substr($title, 0, self::VARCHAR_MAX),
                    'description' => $normalized['description'],
                    'tags' => $normalized['tags'],
                    'edition' => $this->normalizer->edition($path),
                    'url' => $source->getFileUrl($repo, $ref, $path),
                    'content' => $normalized['content'],
                    'source_sha' => $sha,
                ];
            }

            if (!$rows) {
                throw new \RuntimeException('No indexable docs produced (0 rows) — keeping existing corpus');
            }

            $progress('store', 0, count($rows));
            $this->docRepository->replaceAll($rows);
            $progress('store', count($rows), count($rows));

            $this->flagManager->saveFlag(self::FLAG_SHA, $sha);
            $this->flagManager->saveFlag(self::FLAG_SYNCED_AT, time());
            $this->flagManager->deleteFlag(self::FLAG_ERROR);

            return ['indexed' => count($rows), 'sha' => $sha];
        } catch (\Throwable $e) {
            $this->flagManager->saveFlag(self::FLAG_ERROR, $e->getMessage());
            $this->errorLogger->addLog('DocsSync', $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
