<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use MagoAssistant\Mago\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * mago/docs/auth_mode was renamed to mago/docs/source, and its "public" value to "github_public".
 * Carries an existing admin choice over so a GitHub App setup keeps syncing after upgrade.
 */
class MoveDocsAuthModeToSource implements DataPatchInterface
{
    private const OLD_PATH = 'mago/docs/auth_mode';
    private const RENAMED_VALUES = ['public' => 'github_public'];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $rows = $connection->fetchAll(
            $connection->select()->from($table, ['config_id', 'value'])->where('path = ?', self::OLD_PATH)
        );
        foreach ($rows as $row) {
            $value = (string)$row['value'];
            $connection->update(
                $table,
                [
                    'path' => ConfigRepository::XML_PATH_DOCS_SOURCE,
                    'value' => self::RENAMED_VALUES[$value] ?? $value,
                ],
                ['config_id = ?' => (int)$row['config_id']]
            );
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
