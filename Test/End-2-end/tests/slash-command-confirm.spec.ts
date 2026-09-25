/*
 * Copyright © Mago Assistant
 */

import {expect, test} from '@playwright/test';
import ChatPanel from 'Pages/backend/ChatPanel';
import {PROVIDER_ROUND_TRIP_TIMEOUT} from 'Config/timeouts';
import WireMock from 'Services/WireMock';

const chatPanel = new ChatPanel();
const wireMock = new WireMock();

const CACHE_TYPE = 'config_webservice';
const HISTORY_CACHE_TYPE = 'translate';
const HISTORY_CHECK = 'E2E slash history check';

/**
 * A typed slash write goes through the same confirmation card as a write the model proposes
 * (issue #150): the real Stream controller stages it, nothing runs until Allow, and the Confirm
 * controller then runs cache_manager and asks the provider (WireMock) for the follow-up turn.
 * wiremock/mappings/slash-confirm.json only answers "clean again" when that turn carries the
 * tool's own "has been flushed" result, so the reply proves the clean actually ran.
 */
test.describe('Slash command confirmation', () => {
  test('Cleans a cache type only after the admin allows it', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, '/cache clean ' + CACHE_TYPE);

    await expect(chatPanel.confirmButton(page)).toBeVisible({timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await expect(chatPanel.lastAssistantMessage(page)).toContainText('cache_manager');
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(CACHE_TYPE);
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText('Cleaned');

    await chatPanel.confirmButton(page).click();

    await expect(chatPanel.lastAssistantMessage(page)).toContainText(
      'The ' + CACHE_TYPE + ' cache is clean again.',
      {timeout: PROVIDER_ROUND_TRIP_TIMEOUT}
    );
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText('Slash confirm fallback');
  });

  test('Asks what changed instead of flushing every cache', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, '/cache flush');

    await expect(chatPanel.lastAssistantMessage(page)).toContainText(
      'Flushing every cache is rarely what a change needs',
      {timeout: PROVIDER_ROUND_TRIP_TIMEOUT}
    );
    await expect(chatPanel.lastAssistantMessage(page)).toContainText('/cache clean full_page');
    await expect(chatPanel.confirmActions(page)).toHaveCount(0);
  });

  test('Asks which index is meant instead of reindexing everything', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, '/index reindex');

    await expect(chatPanel.lastAssistantMessage(page)).toContainText(
      'Reindexing everything rebuilds every index',
      {timeout: PROVIDER_ROUND_TRIP_TIMEOUT}
    );
    await expect(chatPanel.lastAssistantMessage(page)).toContainText('catalog_product_price');
    await expect(chatPanel.confirmActions(page)).toHaveCount(0);
  });

  test('Refuses a reindex of an unknown indexer without a confirmation card', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, '/index reindex bogus_indexer_id');

    await expect(chatPanel.lastAssistantMessage(page)).toContainText(
      'Unknown indexer "bogus_indexer_id"',
      {timeout: PROVIDER_ROUND_TRIP_TIMEOUT}
    );
    await expect(chatPanel.lastAssistantMessage(page)).toContainText('catalog_product_price');
    await expect(chatPanel.confirmActions(page)).toHaveCount(0);
  });

  /**
   * Every slash write is stored with its tool_calls, and the history sent to the provider pairs a
   * call with its result by id alone. Typing the same clean twice used to reuse the id, so the
   * rejected second call looked answered by the first one's result: the provider then got a
   * tool_call without a tool message (or two identical ids) and refused every later turn.
   */
  test('Sends the provider a history where every slash tool call is unique and answered', async ({page, request}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, '/cache clean ' + HISTORY_CACHE_TYPE);
    await expect(chatPanel.confirmButton(page)).toBeVisible({timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await chatPanel.confirmButton(page).click();
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(
      'The ' + HISTORY_CACHE_TYPE + ' cache is clean again.',
      {timeout: PROVIDER_ROUND_TRIP_TIMEOUT}
    );

    await chatPanel.ask(page, '/cache clean ' + HISTORY_CACHE_TYPE);
    await expect(chatPanel.rejectButton(page)).toBeVisible({timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await chatPanel.rejectButton(page).click();
    await expect(chatPanel.confirmActions(page)).toHaveCount(0, {timeout: PROVIDER_ROUND_TRIP_TIMEOUT});

    await chatPanel.ask(page, HISTORY_CHECK);
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(
      'Slash history reached the provider.',
      {timeout: PROVIDER_ROUND_TRIP_TIMEOUT}
    );

    const body = await wireMock.findRequestBody(request, HISTORY_CHECK);
    const messages: any[] = body.messages ?? [];
    const toolCallIds: string[] = messages.flatMap((message) => (message.tool_calls ?? []).map((call: any) => call.id));
    const answeredIds = new Set(messages.filter((message) => message.role === 'tool').map((message) => message.tool_call_id));

    expect(toolCallIds.length, 'the allowed clean is missing from the history').toBeGreaterThan(0);
    expect(new Set(toolCallIds).size, 'a slash tool call id repeats in the history').toBe(toolCallIds.length);
    for (const id of toolCallIds) {
      expect(answeredIds.has(id), 'tool call ' + id + ' has no tool result in the history').toBe(true);
    }
  });
});
