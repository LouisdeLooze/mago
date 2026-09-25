<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Ai;

use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Logger\DebugLogger;
use MagoAssistant\Mago\Logger\ErrorLogger;
use MagoAssistant\Mago\Service\Ai\AnswerWidgets;
use MagoAssistant\Mago\Service\Ai\ChatService;
use MagoAssistant\Mago\Service\Form\PageContextHolder;
use MagoAssistant\Mago\Service\Privacy\ConversationVault;
use MagoAssistant\Mago\Service\Privacy\PiiHeuristic;
use MagoAssistant\Mago\Service\Privacy\PrivacyFilter;
use MagoAssistant\Mago\Service\Privacy\PrivacyService;
use MagoAssistant\Mago\Service\Store\StoreScopeContext;
use MagoAssistant\Mago\Service\Tool\ToolRegistry;
use MagoAssistant\Mago\Test\Unit\Fakes\BuildsStoreLayouts;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeAction;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeAuthorization;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeChatClient;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeConfigRepository;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeGuidedSkill;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeLogger;
use MagoAssistant\Mago\Test\Unit\Fakes\FakePermissionChecker;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeSkill;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeUsageLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The confirmation card for a typed slash-command write (prepareToolConfirmation) and the upfront
 * tool-guidance section the model reads before its first call
 */
final class ChatServiceToolConfirmationTest extends TestCase
{
    use BuildsStoreLayouts;

    private const ADMIN_ID = 7;

    private const GUIDANCE_MARKER = '[Tool usage guidance]';

    private const CACHE_GUIDANCE = 'Clean only the cache type the change touches.';

    /** @var FakeChatClient Provider stand-in recording every conversation sent to it */
    private FakeChatClient $client;

    /** @var FakePermissionChecker Skill grants of the admin under test */
    private FakePermissionChecker $permissions;

    protected function setUp(): void
    {
        $this->client = new FakeChatClient();
        $this->permissions = (new FakePermissionChecker())
            ->withDecision('cms_data', 'read', true)
            ->withDecision('cms_data', 'write', true);
    }

    #[Test]
    public function itPutsATypedWriteOnTheConfirmationCardWithoutAModelTurn(): void
    {
        $service = $this->buildChatService();
        $events = [];
        $call = ['id' => 'slash_1', 'name' => 'cms_data', 'input' => ['action' => 'update_page', 'content' => 'x']];

        $result = $service->prepareToolConfirmation(
            [$call],
            static function (string $type, array $data) use (&$events): void {
                $events[] = [$type, $data];
            },
            self::ADMIN_ID
        );

        self::assertSame([], $this->client->getRequests());
        self::assertSame('confirm', $events[0][0]);
        self::assertSame(['slash_1'], array_column($events[0][1]['tools'], 'id'));
        self::assertSame('cms_data', $events[0][1]['tools'][0]['name']);
        self::assertSame('x', $events[0][1]['tools'][0]['input']['content']);
        self::assertSame('', $result['content']);
        self::assertTrue($result['pending_confirmation']);
        self::assertSame($events[0][1]['tools'][0]['description'], $result['tool_calls'][0]['description']);
        self::assertSame($call['input'], $result['tool_calls'][0]['input']);
    }

    #[Test]
    public function itCarriesACallThatNeedsNoConfirmationThroughUndescribed(): void
    {
        $service = $this->buildChatService();
        $confirm = null;
        $call = ['id' => 'slash_1', 'name' => 'cms_data', 'input' => ['action' => 'list_pages']];

        $result = $service->prepareToolConfirmation(
            [$call],
            static function (string $type, array $data) use (&$confirm): void {
                $confirm = $data;
            },
            self::ADMIN_ID
        );

        self::assertSame(['tools' => []], $confirm);
        self::assertSame([$call], $result['tool_calls']);
    }

    #[Test]
    public function itReturnsTheSameDescribedCallsWithoutAStreamCallback(): void
    {
        $service = $this->buildChatService();
        $call = ['id' => 'slash_1', 'name' => 'cms_data', 'input' => ['action' => 'update_page', 'content' => 'x']];

        $result = $service->prepareToolConfirmation([$call], null, self::ADMIN_ID);

        self::assertTrue($result['pending_confirmation']);
        self::assertSame('slash_1', $result['tool_calls'][0]['id']);
        self::assertArrayHasKey('description', $result['tool_calls'][0]);
    }

    #[Test]
    public function itGivesTheModelTheUpfrontGuidanceOfAToolTheAdminMayUse(): void
    {
        $this->permissions->withDecision('cache_manager', 'write', true);
        $service = $this->buildChatService([$this->guidedSkill('cache_manager', self::CACHE_GUIDANCE)]);

        $service->processMessage([$this->userMessage()], null, self::ADMIN_ID);

        self::assertStringContainsString(
            self::GUIDANCE_MARKER . "\n- cache_manager: " . self::CACHE_GUIDANCE,
            $this->systemText($this->client->getRequests()[0])
        );
    }

    #[Test]
    public function itLeavesOutTheGuidanceOfAToolTheAdminMayNotUse(): void
    {
        $this->permissions
            ->withDecision('cache_manager', 'write', true)
            ->withDecision('indexer_manager', 'write', false);
        $service = $this->buildChatService([
            $this->guidedSkill('cache_manager', self::CACHE_GUIDANCE),
            $this->guidedSkill('indexer_manager', 'Ask which index is meant.'),
        ]);

        $service->processMessage([$this->userMessage()], null, self::ADMIN_ID);

        $systemText = $this->systemText($this->client->getRequests()[0]);
        self::assertStringContainsString('- cache_manager: ', $systemText);
        self::assertStringNotContainsString('indexer_manager', $systemText);
    }

    #[Test]
    public function itAddsNoGuidanceSectionWhenNoAvailableToolCarriesGuidance(): void
    {
        $this->permissions->withDecision('cache_manager', 'write', true);
        $service = $this->buildChatService([$this->guidedSkill('cache_manager', '  ')]);

        $service->processMessage([$this->userMessage()], null, self::ADMIN_ID);

        self::assertStringNotContainsString(
            self::GUIDANCE_MARKER,
            $this->systemText($this->client->getRequests()[0])
        );
    }

    #[Test]
    public function itDoesNotRepeatTheGuidanceSectionTheConversationAlreadyCarries(): void
    {
        $this->permissions->withDecision('cache_manager', 'write', true);
        $service = $this->buildChatService([$this->guidedSkill('cache_manager', self::CACHE_GUIDANCE)]);
        $earlierSection = ['role' => 'system', 'content' => self::GUIDANCE_MARKER . "\n- cache_manager: earlier"];

        $service->processMessage([$earlierSection, $this->userMessage()], null, self::ADMIN_ID);

        $systemText = $this->systemText($this->client->getRequests()[0]);
        self::assertSame(1, substr_count($systemText, self::GUIDANCE_MARKER));
        self::assertStringNotContainsString(self::CACHE_GUIDANCE, $systemText);
    }

    /**
     * @param FakeSkill[]|FakeGuidedSkill[] $extraSkills
     */
    private function buildChatService(array $extraSkills = []): ChatService
    {
        $authorization = new FakeAuthorization();
        $cmsData = new FakeSkill('cms_data', $authorization, [
            'list_pages' => new FakeAction('list_pages', true),
            'update_page' => new FakeAction('update_page', false, ['content' => ['type' => 'string']]),
        ]);

        $json = new Json();
        $vault = new ConversationVault();

        return new ChatService(
            (new FakeConfigRepository())->withMaxToolIterations(5)->withMaxResponseTokens(4000),
            $this->client,
            new ToolRegistry($this->permissions, array_merge([$cmsData], $extraSkills)),
            new DebugLogger(new FakeLogger(), $json),
            new ErrorLogger(new FakeLogger(), $json),
            new FakeUsageLogger(),
            $authorization,
            new StoreScopeContext($this->singleStoreManager()),
            new AnswerWidgets(),
            new PageContextHolder(),
            new PrivacyService(new PrivacyFilter($vault, new PiiHeuristic()), $vault, new PiiHeuristic())
        );
    }

    private function guidedSkill(string $name, string $guidance): FakeGuidedSkill
    {
        return new FakeGuidedSkill($name, $guidance, new FakeAuthorization(), [
            'flush' => new FakeAction('flush', false),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function userMessage(): array
    {
        return ['role' => 'user', 'content' => 'Clean the config cache'];
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    private function systemText(array $messages): string
    {
        $system = array_filter($messages, static fn (array $m): bool => ($m['role'] ?? '') === 'system');

        return implode("\n\n", array_map(static fn (array $m): string => (string)$m['content'], $system));
    }
}
