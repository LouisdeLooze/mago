<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Tool;

use Magento\Framework\AuthorizationInterface;
use MagoAssistant\Mago\Service\Skills\PermissionChecker;
use MagoAssistant\Mago\Service\Tool\ToolRegistry;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeAction;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeAvailabilityAwareTool;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeAuthorization;
use MagoAssistant\Mago\Test\Unit\Fakes\FakePermissionChecker;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeSkill;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolRegistryTest extends TestCase
{
    private const ADMIN_ID = 7;

    private FakeSkill $cmsData;
    private FakeTool $cacheManager;

    protected function setUp(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $this->cmsData = new FakeSkill('cms_data', $authorization, [
            'list_pages' => new FakeAction('list_pages', true, ['limit' => ['type' => 'integer']]),
            'update_page' => new FakeAction('update_page', false, [
                'limit' => ['type' => 'integer'],
                'content' => ['type' => 'string'],
            ]),
        ]);
        $this->cacheManager = new FakeTool('cache_manager', ['status', 'flush'], ['status']);
    }

    #[Test]
    public function readGrantNarrowsDescriptionEnumAndParamsOfActionScopedTool(): void
    {
        $registry = $this->registry(['cms_data' => 'read']);

        $definition = $registry->getToolDefinition($this->cmsData, self::ADMIN_ID);

        self::assertSame('Fake cms_data. Actions: "list_pages" (list_pages description).', $definition['description']);
        self::assertSame(['list_pages'], $definition['parameters']['properties']['action']['enum']);
        self::assertArrayHasKey('limit', $definition['parameters']['properties']);
        self::assertArrayNotHasKey('content', $definition['parameters']['properties']);
    }

    #[Test]
    public function writeGrantAdvertisesTheFullTool(): void
    {
        $registry = $this->registry(['cms_data' => 'write']);

        $definition = $registry->getToolDefinition($this->cmsData, self::ADMIN_ID);

        self::assertStringContainsString('"update_page"', $definition['description']);
        self::assertSame(['list_pages', 'update_page'], $definition['parameters']['properties']['action']['enum']);
        self::assertArrayHasKey('content', $definition['parameters']['properties']);
    }

    #[Test]
    public function readGrantOnlyFiltersTheEnumOfAToolThatIsNotActionScoped(): void
    {
        $registry = $this->registry(['cache_manager' => 'read']);

        $definition = $registry->getToolDefinition($this->cacheManager, self::ADMIN_ID);

        self::assertSame(['status'], $definition['parameters']['properties']['action']['enum']);
        self::assertSame($this->cacheManager->getDescription(), $definition['description']);
    }

    #[Test]
    public function toolDefinitionsOnlyContainToolsTheUserMayInvoke(): void
    {
        $registry = $this->registry(['cms_data' => 'read', 'cache_manager' => 'disabled']);

        $definitions = $registry->getToolDefinitions(self::ADMIN_ID);

        self::assertSame(['cms_data'], array_column($definitions, 'name'));
    }

    #[Test]
    public function writeAccessFollowsTheGrant(): void
    {
        $registry = $this->registry(['cms_data' => 'read', 'cache_manager' => 'write']);

        self::assertFalse($registry->hasWriteAccess($this->cmsData, self::ADMIN_ID));
        self::assertTrue($registry->hasWriteAccess($this->cacheManager, self::ADMIN_ID));
    }

    #[Test]
    public function parameterSchemaIsBuiltOncePerToolPerRequest(): void
    {
        $registry = $this->registry(['cms_data' => 'read', 'cache_manager' => 'read']);

        $registry->getToolDefinitions(self::ADMIN_ID);
        $registry->getEnabledTools(self::ADMIN_ID);
        $registry->getTool('cache_manager', self::ADMIN_ID);
        $registry->getToolDefinition($this->cacheManager, self::ADMIN_ID);

        self::assertSame(1, $this->cacheManager->getSchemaCalls());
    }

    #[Test]
    public function withoutPermissionCheckerEverythingIsAdvertisedUnfiltered(): void
    {
        $registry = new ToolRegistry(null, [$this->cmsData, $this->cacheManager]);

        $definitions = $registry->getToolDefinitions(self::ADMIN_ID);

        self::assertCount(2, $definitions);
        self::assertSame(['list_pages', 'update_page'], $definitions[0]['parameters']['properties']['action']['enum']);
        self::assertTrue($registry->hasWriteAccess($this->cmsData, self::ADMIN_ID));
    }

    /**
     * @param array<string, string> $grants skill name => read|write|disabled
     */
    private function registry(array $grants): ToolRegistry
    {
        $checker = $this->createMock(PermissionChecker::class);
        $checker->method('isAllowed')->willReturnCallback(
            static function (int $adminUserId, string $skill, string $action) use ($grants): bool {
                if ($adminUserId !== self::ADMIN_ID) {
                    return false;
                }
                return match ($grants[$skill] ?? 'disabled') {
                    'write' => true,
                    'read' => $action === 'read',
                    default => false,
                };
            }
        );

        return new ToolRegistry($checker, [$this->cmsData, $this->cacheManager]);
    }

    private const ADMIN_USER_ID = 42;

    private function readOnlySkill(): FakeSkill
    {
        return new FakeSkill('read_only_skill', new FakeAuthorization(), [
            'view' => new FakeAction('view', true),
        ]);
    }

    private function writeOnlySkill(): FakeSkill
    {
        return new FakeSkill('write_only_skill', new FakeAuthorization(), [
            'do' => new FakeAction('do', false),
        ]);
    }

    private function mixedSkill(): FakeSkill
    {
        return new FakeSkill('mixed_skill', new FakeAuthorization(), [
            'view' => new FakeAction('view', true),
            'do' => new FakeAction('do', false),
        ]);
    }

    #[Test]
    public function itHidesAReadOnlyToolWhenTheGrantIsDisabled(): void
    {
        $tool = $this->readOnlySkill();
        $permissionChecker = (new FakePermissionChecker())->withDecision('read_only_skill', 'read', false);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertSame([], $registry->getEnabledTools(self::ADMIN_USER_ID));
        self::assertNull($registry->getTool('read_only_skill', self::ADMIN_USER_ID));
    }

    #[Test]
    public function itShowsAReadOnlyToolWithOnlyAReadGrant(): void
    {
        $tool = $this->readOnlySkill();
        $permissionChecker = (new FakePermissionChecker())->withDecision('read_only_skill', 'read', true);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertSame(['read_only_skill' => $tool], $registry->getEnabledTools(self::ADMIN_USER_ID));
        self::assertSame($tool, $registry->getTool('read_only_skill', self::ADMIN_USER_ID));
    }

    #[Test]
    public function itHidesAWriteOnlyToolWhenTheUserOnlyHasReadGrant(): void
    {
        $tool = $this->writeOnlySkill();
        $permissionChecker = (new FakePermissionChecker())
            ->withDecision('write_only_skill', 'read', true)
            ->withDecision('write_only_skill', 'write', false);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertSame([], $registry->getEnabledTools(self::ADMIN_USER_ID));
    }

    #[Test]
    public function itShowsAWriteOnlyToolOnlyWithAWriteGrant(): void
    {
        $tool = $this->writeOnlySkill();
        $permissionChecker = (new FakePermissionChecker())->withDecision('write_only_skill', 'write', true);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertSame(['write_only_skill' => $tool], $registry->getEnabledTools(self::ADMIN_USER_ID));
    }

    #[Test]
    public function itShowsAMixedToolWithOnlyAReadGrant(): void
    {
        $tool = $this->mixedSkill();
        $permissionChecker = (new FakePermissionChecker())->withDecision('mixed_skill', 'read', true);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertSame(['mixed_skill' => $tool], $registry->getEnabledTools(self::ADMIN_USER_ID));
    }

    #[Test]
    public function itFiltersTheActionEnumOfAMixedToolToReadActionsWithoutWriteGrant(): void
    {
        $tool = $this->mixedSkill();
        $permissionChecker = (new FakePermissionChecker())
            ->withDecision('mixed_skill', 'read', true)
            ->withDecision('mixed_skill', 'write', false);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        $definitions = $registry->getToolDefinitions(self::ADMIN_USER_ID);

        self::assertSame(['view'], $definitions[0]['parameters']['properties']['action']['enum']);
    }

    #[Test]
    public function itKeepsTheFullActionEnumOfAMixedToolWithAWriteGrant(): void
    {
        $tool = $this->mixedSkill();
        $permissionChecker = (new FakePermissionChecker())
            ->withDecision('mixed_skill', 'read', true)
            ->withDecision('mixed_skill', 'write', true);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        $definitions = $registry->getToolDefinitions(self::ADMIN_USER_ID);

        self::assertSame(['view', 'do'], $definitions[0]['parameters']['properties']['action']['enum']);
    }

    #[Test]
    public function itAllowsAReadActionOnAMixedToolWithOnlyAReadGrant(): void
    {
        $tool = $this->mixedSkill();
        $permissionChecker = (new FakePermissionChecker())
            ->withDecision('mixed_skill', 'read', true)
            ->withDecision('mixed_skill', 'write', false);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertTrue($registry->isCallAllowed($tool, ['action' => 'view'], self::ADMIN_USER_ID));
        self::assertFalse($registry->isCallAllowed($tool, ['action' => 'do'], self::ADMIN_USER_ID));
    }

    #[Test]
    public function itTreatsAMissingActionOnAMixedToolAsWrite(): void
    {
        $tool = $this->mixedSkill();
        $permissionChecker = (new FakePermissionChecker())
            ->withDecision('mixed_skill', 'read', true)
            ->withDecision('mixed_skill', 'write', false);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertFalse($registry->isCallAllowed($tool, [], self::ADMIN_USER_ID));
    }

    #[Test]
    public function itTreatsAnUnknownActionOnAMixedToolAsWrite(): void
    {
        $tool = $this->mixedSkill();
        $permissionChecker = (new FakePermissionChecker())
            ->withDecision('mixed_skill', 'read', true)
            ->withDecision('mixed_skill', 'write', false);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertFalse($registry->isCallAllowed($tool, ['action' => 'bogus'], self::ADMIN_USER_ID));
    }

    #[Test]
    public function itAlwaysChecksReadForAReadOnlyToolRegardlessOfInput(): void
    {
        $tool = $this->readOnlySkill();
        $permissionChecker = (new FakePermissionChecker())->withDecision('read_only_skill', 'read', true);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertTrue($registry->isCallAllowed($tool, [], self::ADMIN_USER_ID));
        self::assertTrue($registry->isCallAllowed($tool, ['action' => 'bogus'], self::ADMIN_USER_ID));
    }

    #[Test]
    public function itAlwaysChecksWriteForAWriteOnlyToolRegardlessOfInput(): void
    {
        $tool = $this->writeOnlySkill();
        $permissionChecker = (new FakePermissionChecker())
            ->withDecision('write_only_skill', 'read', true)
            ->withDecision('write_only_skill', 'write', false);
        $registry = new ToolRegistry($permissionChecker, [$tool]);

        self::assertFalse($registry->isCallAllowed($tool, [], self::ADMIN_USER_ID));
        self::assertFalse($registry->isCallAllowed($tool, ['action' => 'do'], self::ADMIN_USER_ID));
    }

    #[Test]
    public function itAllowsEverythingWhenNoPermissionCheckerIsWired(): void
    {
        $tool = $this->mixedSkill();
        $registry = new ToolRegistry(null, [$tool]);

        self::assertSame(['mixed_skill' => $tool], $registry->getEnabledTools(self::ADMIN_USER_ID));
        self::assertSame($tool, $registry->getTool('mixed_skill', self::ADMIN_USER_ID));
        self::assertTrue($registry->isCallAllowed($tool, ['action' => 'view'], self::ADMIN_USER_ID));
        self::assertTrue($registry->isCallAllowed($tool, ['action' => 'do'], self::ADMIN_USER_ID));

        $definitions = $registry->getToolDefinitions(self::ADMIN_USER_ID);
        self::assertSame(['view', 'do'], $definitions[0]['parameters']['properties']['action']['enum']);
    }

    #[Test]
    public function itLeavesOutToolsThatAreNotAvailableOnThisStore(): void
    {
        $available = new FakeAvailabilityAwareTool('stock_level_msi', true);
        $unavailable = new FakeAvailabilityAwareTool('stock_level', false);
        $registry = new ToolRegistry(null, [$available, $unavailable]);

        self::assertSame([$available], array_values($registry->getAllTools()));
        self::assertSame(['stock_level_msi' => $available], $registry->getEnabledTools(self::ADMIN_USER_ID));
        self::assertNull($registry->getToolByName('stock_level'));
        self::assertNull($registry->getTool('stock_level', self::ADMIN_USER_ID));
    }
}
