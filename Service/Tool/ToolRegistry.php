<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Tool;

use MagoAssistant\Mago\Api\Tool\ActionScopedToolInterface;
use MagoAssistant\Mago\Api\Tool\AvailabilityAwareToolInterface;
use MagoAssistant\Mago\Api\Tool\ToolInterface;
use MagoAssistant\Mago\Service\Skills\PermissionChecker;

class ToolRegistry
{
    /** @var ToolInterface[] */
    private array $tools;

    /** @var array<string, array<string, mixed>> Unfiltered parameter schema per tool name */
    private array $schemaCache = [];

    /** @var array<string, string[]> Read-only action names per tool name */
    private array $readActionsCache = [];

    /**
     * @param PermissionChecker|null $permissionChecker
     * @param ToolInterface[] $tools
     */
    public function __construct(
        private readonly ?PermissionChecker $permissionChecker = null,
        array $tools = []
    ) {
        $this->tools = array_filter(
            $tools,
            static fn (ToolInterface $tool): bool => !$tool instanceof AvailabilityAwareToolInterface
                || $tool->isAvailable()
        );
    }

    /**
     * Get all registered tools that are available on this store (regardless of enabled state)
     *
     * @return ToolInterface[]
     */
    public function getAllTools(): array
    {
        return $this->tools;
    }

    /**
     * Get a tool by name (from all registered tools)
     */
    public function getToolByName(string $name): ?ToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }
        return null;
    }

    /**
     * Get all enabled tools (filtered by DB permission check for a given admin user)
     *
     * @return ToolInterface[]
     */
    public function getEnabledTools(?int $adminUserId = null): array
    {
        $enabled = [];
        foreach ($this->tools as $tool) {
            if (!$this->isToolAvailable($tool, $adminUserId)) {
                continue;
            }
            $enabled[$tool->getName()] = $tool;
        }
        return $enabled;
    }

    /**
     * Get tool definitions for AI provider
     *
     * @param int|null $adminUserId
     * @return array<int, array<string, mixed>>
     */
    public function getToolDefinitions(?int $adminUserId = null): array
    {
        $definitions = [];
        foreach ($this->getEnabledTools($adminUserId) as $tool) {
            $definitions[] = $this->getToolDefinition($tool, $adminUserId);
        }
        return $definitions;
    }

    /**
     * Definition of one tool as the given admin user may use it.
     *
     * A user without write access to a mixed tool gets a definition narrowed to
     * the read-only actions: the action enum, and for action-scoped tools also
     * the description and the parameters, so denied write actions are not
     * advertised to the model at all.
     *
     * @param ToolInterface $tool
     * @param int|null $adminUserId
     * @return array{name: string, description: string, parameters: array<string, mixed>}
     */
    public function getToolDefinition(ToolInterface $tool, ?int $adminUserId): array
    {
        $description = $tool->getDescription();
        $schema = $this->getSchema($tool);

        if (!$tool->isReadOnly() && !$this->hasWriteAccess($tool, $adminUserId)) {
            $readActions = $this->getReadActionNames($tool);
            if ($readActions !== []) {
                if ($tool instanceof ActionScopedToolInterface) {
                    $description = $tool->getDescriptionForActions($readActions);
                    $schema = $tool->getParameterSchemaForActions($readActions);
                } elseif (isset($schema['properties']['action']['enum'])) {
                    $schema['properties']['action']['enum'] = $readActions;
                }
            }
        }

        return [
            'name' => $tool->getName(),
            'description' => $description,
            'parameters' => $schema,
        ];
    }

    /**
     * Get a tool by name (from enabled tools only)
     *
     * @param string $name
     * @param int|null $adminUserId
     * @return ToolInterface|null
     */
    public function getTool(string $name, ?int $adminUserId = null): ?ToolInterface
    {
        $tool = $this->getToolByName($name);
        if ($tool === null || !$this->isToolAvailable($tool, $adminUserId)) {
            return null;
        }
        return $tool;
    }

    /**
     * Whether a specific invocation (tool + input) is allowed for the admin user.
     * A missing user id routes through the ACL fallback instead of allowing everything.
     *
     * @param ToolInterface $tool
     * @param array<string, mixed> $input
     * @param int|null $adminUserId
     * @return bool
     */
    public function isCallAllowed(ToolInterface $tool, array $input, ?int $adminUserId): bool
    {
        if ($this->permissionChecker === null) {
            return true;
        }
        $action = $tool->isReadOnlyAction($input) ? 'read' : 'write';
        return $this->permissionChecker->isAllowed($adminUserId ?? 0, $tool->getName(), $action);
    }

    /**
     * Whether the admin user holds a write grant for the tool
     *
     * @param ToolInterface $tool
     * @param int|null $adminUserId
     * @return bool
     */
    public function hasWriteAccess(ToolInterface $tool, ?int $adminUserId): bool
    {
        if ($this->permissionChecker === null) {
            return true;
        }
        return $this->permissionChecker->isAllowed($adminUserId ?? 0, $tool->getName(), 'write');
    }

    private function isToolAvailable(ToolInterface $tool, ?int $adminUserId): bool
    {
        if ($this->permissionChecker === null) {
            return true;
        }
        $action = $this->supportsReadAction($tool) ? 'read' : 'write';
        return $this->permissionChecker->isAllowed($adminUserId ?? 0, $tool->getName(), $action);
    }

    /**
     * Whether the tool can be invoked without write permission (fully read-only or mixed skill)
     */
    private function supportsReadAction(ToolInterface $tool): bool
    {
        return $tool->isReadOnly() || $this->getReadActionNames($tool) !== [];
    }

    /**
     * Unfiltered parameter schema, built once per tool per request
     *
     * @param ToolInterface $tool
     * @return array<string, mixed>
     */
    private function getSchema(ToolInterface $tool): array
    {
        $name = $tool->getName();
        if (!array_key_exists($name, $this->schemaCache)) {
            $this->schemaCache[$name] = $tool->getParameterSchema();
        }
        return $this->schemaCache[$name];
    }

    /**
     * Names of the tool's read-only actions, derived once per tool per request
     *
     * @param ToolInterface $tool
     * @return string[]
     */
    private function getReadActionNames(ToolInterface $tool): array
    {
        $name = $tool->getName();
        if (!array_key_exists($name, $this->readActionsCache)) {
            $readActions = [];
            $enum = $this->getSchema($tool)['properties']['action']['enum'] ?? [];
            foreach (is_array($enum) ? $enum : [] as $actionName) {
                if ($tool->isReadOnlyAction(['action' => $actionName])) {
                    $readActions[] = $actionName;
                }
            }
            $this->readActionsCache[$name] = $readActions;
        }
        return $this->readActionsCache[$name];
    }
}
