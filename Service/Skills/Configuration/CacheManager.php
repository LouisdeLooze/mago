<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Configuration;

use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Cache\TypeListInterface;
use MagoAssistant\Mago\Api\Tool\ActionScopedToolInterface;
use MagoAssistant\Mago\Api\Tool\UpfrontGuidanceToolInterface;
use MagoAssistant\Mago\Api\Tool\ValidatingToolInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

class CacheManager implements ActionScopedToolInterface, UpfrontGuidanceToolInterface, ValidatingToolInterface
{
    /** A cache type echoed into an error is capped here so a runaway argument is not stored or re-sent */
    private const MAX_ID_ECHO = 100;

    private const ACTION_DESCRIPTIONS = [
        'status' => 'list all cache types and their status',
        'flush' => 'flush all caches',
        'flush_type' => 'flush a specific cache type by id, e.g. "config", "full_page", "layout", "block_html"',
    ];

    /**
     * Steer the model away from a blanket flush and towards the narrowest cache that answers the
     * request. It is in the description, not getInstructions(), because the description is always in
     * the tool schema, while getInstructions() is injected only after a call has run — too late to
     * stop the flush it should have questioned.
     */
    private const PUSHBACK = 'Prefer flush_type for the specific cache the change affects over flush '
        . '(everything). full_page holds rendered pages — a simple product page, a category/PLP page, '
        . 'a CMS page or a search-results page; block_html and layout hold block and layout output; '
        . 'config holds configuration. When the user names or links a specific product, category, CMS '
        . 'page or search result, deduce the entity and clear only the cache it affects (usually '
        . 'full_page) rather than flushing all caches. If it is unclear which cache or which page the '
        . 'user means, ask which one before acting. '
        . 'flush_type clears the ENTIRE named cache type; there is no per-page, per-URL or per-entity '
        . 'cache flush in Magento. So once you know the cache TYPE — a product page, a category/PLP, a '
        . 'CMS page and a search-results page all map to full_page — you have everything you need: do '
        . 'NOT keep asking for a specific page URL or id, that granularity does not exist. Do not read '
        . 'the on-screen form to decide this; the current admin page is unrelated to which storefront '
        . 'cache to clear. '
        . 'Once you know which cache type is needed, DO IT: call cache_manager with action "flush_type" '
        . 'and that cache id yourself. The write is not executed until the admin approves it on a '
        . 'confirmation card, so proposing the call IS the safe, correct step. Do not answer with '
        . 'instructions telling the admin to type a "/cache" slash command or to click through the '
        . 'admin — you perform the flush_type call, they confirm it. '
        . 'And do NOT ask "would you like me to proceed?", "shall I clear it?" or any yes/no in text '
        . 'before the write: that question is exactly what the confirmation card asks. Make the '
        . 'flush_type call immediately; the card is the admin\'s yes/no. Asking first in prose and '
        . 'waiting for a reply is wrong — it just adds a step before the same card.';

    public function __construct(
        private readonly TypeListInterface $cacheTypeList,
        private readonly CacheFrontendPool $cacheFrontendPool
    ) {
    }

    public function getName(): string
    {
        return 'cache_manager';
    }

    public function getDescription(): string
    {
        return $this->getDescriptionForActions(array_keys(self::ACTION_DESCRIPTIONS));
    }

    public function getDescriptionForActions(array $actionNames): string
    {
        $parts = [];
        foreach (self::ACTION_DESCRIPTIONS as $name => $description) {
            if (in_array($name, $actionNames, true)) {
                $parts[] = '"' . $name . '" (' . $description . ')';
            }
        }

        return 'Manage Magento caches. Actions: ' . implode(', ', $parts) . '.';
    }

    public function getParameterSchema(): array
    {
        return $this->getParameterSchemaForActions(array_keys(self::ACTION_DESCRIPTIONS));
    }

    public function getParameterSchemaForActions(array $actionNames): array
    {
        $properties = [
            'action' => [
                'type' => 'string',
                'description' => 'The action to perform',
                'enum' => array_values(array_intersect(array_keys(self::ACTION_DESCRIPTIONS), $actionNames)),
            ],
        ];
        if (in_array('flush_type', $actionNames, true)) {
            // The real cache types are known here, so they are offered as an enum rather than a free
            // string: the model cannot invent a type that then fails only after the admin confirms it.
            $cacheTypes = $this->cacheTypeLabels();
            $properties['cache_type'] = [
                'type' => 'string',
                'description' => 'Cache type to flush for flush_type. Use one of these exact IDs'
                    . ($cacheTypes === [] ? '.' : ': ' . $this->formatIdList($cacheTypes) . '.')
                    . ' Do not invent an ID; run the "status" action if unsure.',
            ];
            if ($cacheTypes !== []) {
                $properties['cache_type']['enum'] = array_keys($cacheTypes);
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => ['action'],
        ];
    }

    public function execute(array $params): array
    {
        $action = $params['action'] ?? '';

        return match ($action) {
            'status' => $this->getStatus(),
            'flush' => $this->flushAll(),
            'flush_type' => $this->flushType($params['cache_type'] ?? ''),
            default => ['error' => 'Unknown action: ' . $action],
        };
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function isReadOnlyAction(array $input): bool
    {
        // status only reads cache state; flush/flush_type mutate. Unknown actions fail closed to write.
        return ($input['action'] ?? '') === 'status';
    }

    /**
     * Refuse a flush_type whose cache_type is not a real type before the confirmation card, answering
     * with the valid list so the model can correct itself rather than have the admin allow a card that
     * only fails on execution (and then guess again, or escalate to a full flush).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public function findRefusal(array $input): ?array
    {
        if (($input['action'] ?? '') !== 'flush_type') {
            return null;
        }
        $cacheType = (string)($input['cache_type'] ?? '');
        // An empty type is the "parameter is required" case, answered by execute(), not an invented one.
        if ($cacheType === '') {
            return null;
        }
        $types = $this->cacheTypeLabels();
        if (isset($types[$cacheType])) {
            return null;
        }

        return [
            'error' => sprintf('Unknown cache type "%s". It is not one of this store\'s cache types.', $this->truncateId($cacheType)),
            'valid_cache_types' => $this->idTitlePairs($types),
        ];
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function getUpfrontGuidance(): string
    {
        return self::PUSHBACK;
    }

    public function getFieldClassification(string $action = ''): array
    {
        return [
            'message' => [PiiClass::PUBLIC],
            'id' => [PiiClass::PUBLIC],
            'label' => [PiiClass::PUBLIC],
            'status' => [PiiClass::PUBLIC],
            'success' => [PiiClass::PUBLIC],
            'flushed' => [PiiClass::PUBLIC],
            'valid_cache_types' => [PiiClass::PUBLIC],
        ];
    }

    public function getMagentoAcl(array $input = []): string
    {
        // Mirrors the native Cache controllers: viewing the grid needs the parent
        // resource, FlushAll needs flush_cache_storage, MassRefresh needs
        // refresh_cache_type. Unknown actions fail closed to the flush resource.
        return match ($input['action'] ?? '') {
            'status' => 'Magento_Backend::cache',
            'flush_type' => 'Magento_Backend::refresh_cache_type',
            default => 'Magento_Backend::flush_cache_storage',
        };
    }

    private function getStatus(): array
    {
        $types = $this->cacheTypeList->getTypes();
        $result = [];
        foreach ($types as $type) {
            $result[] = [
                'id' => $type->getId(),
                'label' => (string)$type->getCacheType(),
                'status' => $type->getStatus() ? 'enabled' : 'disabled',
            ];
        }
        return ['cache_types' => $result];
    }

    private function flushAll(): array
    {
        $types = $this->cacheTypeList->getTypes();
        $flushed = [];
        foreach ($types as $type) {
            $this->cacheTypeList->cleanType($type->getId());
            $flushed[] = $type->getId();
        }
        foreach ($this->cacheFrontendPool as $frontend) {
            $frontend->getBackend()->clean();
        }
        return [
            'success' => true,
            'message' => 'All caches have been flushed',
            'flushed' => $flushed,
        ];
    }

    private function flushType(string $cacheType): array
    {
        if (!$cacheType) {
            return ['error' => 'cache_type parameter is required for flush_type action'];
        }

        $types = $this->cacheTypeList->getTypes();
        if (!isset($types[$cacheType])) {
            return ['error' => 'Unknown cache type: ' . $this->truncateId($cacheType)
                . '. Use "status" action to list available types.'];
        }

        $this->cacheTypeList->cleanType($cacheType);

        return [
            'success' => true,
            'message' => sprintf('Cache type "%s" has been flushed', $cacheType),
        ];
    }

    /**
     * Live cache types as id => label, or an empty list if they cannot be read. A failure here must
     * not break the tool schema (which is built on every chat request), so the id then falls back to
     * a free string rather than taking the assistant down.
     *
     * @return array<string, string>
     */
    private function cacheTypeLabels(): array
    {
        try {
            $labels = [];
            foreach ($this->cacheTypeList->getTypes() as $type) {
                $labels[(string)$type->getId()] = (string)$type->getCacheType();
            }

            return $labels;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string, string> $types id => label
     * @return array<int, array{id: string, label: string}>
     */
    private function idTitlePairs(array $types): array
    {
        $pairs = [];
        foreach ($types as $id => $label) {
            $pairs[] = ['id' => $id, 'label' => $label];
        }

        return $pairs;
    }

    /**
     * "id (Label)" for each cache type, for the parameter description
     *
     * @param array<string, string> $types id => label
     */
    private function formatIdList(array $types): string
    {
        $parts = [];
        foreach ($types as $id => $label) {
            $parts[] = $label === '' ? $id : sprintf('%s (%s)', $id, $label);
        }

        return implode(', ', $parts);
    }

    private function truncateId(string $id): string
    {
        return mb_strlen($id) > self::MAX_ID_ECHO ? mb_substr($id, 0, self::MAX_ID_ECHO) . '…' : $id;
    }
}
