/*
 * Copyright © Mago Assistant
 */

import {expect, test} from '@playwright/test';
import ChatPanel from 'Pages/backend/ChatPanel';
import {PROVIDER_ROUND_TRIP_TIMEOUT} from 'Config/timeouts';

const chatPanel = new ChatPanel();

/**
 * The request runs through the real controller and ChatService; only the provider is WireMock
 * (wiremock/mappings/unknown-tool-id.json). The mocked model asks for a write on an id the store
 * does not have. The tool refuses it before any confirmation card, and the provider only answers
 * with the corrected suggestion when the next request carries that refusal and the valid list (#131).
 */
test.describe('Unknown tool id', () => {
  test('Refuses a reindex of an unknown indexer without a confirmation card', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, 'Please run the E2E unknown indexer id check.');

    await expect(chatPanel.lastAssistantMessage(page))
      .toContainText('The stock indexer is cataloginventory_stock', {timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText('was not refused');
    await expect(chatPanel.confirmActions(page)).toHaveCount(0);
  });

  test('Refuses a flush of an unknown cache type without a confirmation card', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, 'Please run the E2E unknown cache type check.');

    await expect(chatPanel.lastAssistantMessage(page))
      .toContainText('The page cache type is full_page', {timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText('was not refused');
    await expect(chatPanel.confirmActions(page)).toHaveCount(0);
  });
});
