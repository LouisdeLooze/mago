/*
 * Copyright © Mago Assistant
 */

import {expect, test} from '@playwright/test';
import ChatPanel from 'Pages/backend/ChatPanel';
import WireMock from 'Services/WireMock';
import {PROVIDER_ROUND_TRIP_TIMEOUT} from 'Config/timeouts';

const chatPanel = new ChatPanel();
const wireMock = new WireMock();

const TRIGGER = 'E2E raw data guard check';
const RE_PRESENT_NUDGE = 'printed raw structured data';
const RE_PRESENTED_ANSWER = 'Order E2E-RAW-1 came to 39.64 in total.';
const RAW_DUMP_FRAGMENT = '"grand_total"';

/**
 * A model that pastes a raw JSON record into its answer streams it to the panel before the server
 * can judge the finished reply. The server then emits `replace`, asks the model to re-present the
 * data, and streams the clean answer into the cleared message. WireMock serves the dump on the
 * first turn and prose once the re-present nudge is in the request.
 */
test.describe('Raw data guard', () => {
  test('it replaces a streamed raw JSON answer with the re-presented one', async ({page, request}) => {
    test.setTimeout(90000);

    await wireMock.ensureReachable(request);
    await chatPanel.openOnDashboard(page);
    const streamResponse = page.waitForResponse((response) => response.url().includes('/mago/chat/stream'));

    await chatPanel.askAndAwaitReply(page, TRIGGER + ': show the last order');

    const streamBody = await (await streamResponse).text();
    expect(streamBody).toContain('event: replace');
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(RE_PRESENTED_ANSWER, {timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText(RAW_DUMP_FRAGMENT);
    await expect(chatPanel.assistantMessages(page)).toHaveCount(1);
    const rePresentRequest = await wireMock.findRequestBody(request, TRIGGER, (body) => body.includes(RE_PRESENT_NUDGE));
    const replayedDump = rePresentRequest.messages.filter(
      (message: {role: string; content: unknown}) => message.role === 'assistant' && String(message.content).includes(RAW_DUMP_FRAGMENT)
    );
    expect(replayedDump).toHaveLength(1);
  });

  test('it stores only the re-presented answer in the conversation', async ({page, request}) => {
    test.setTimeout(90000);

    await wireMock.ensureReachable(request);
    await chatPanel.openOnDashboard(page);
    await chatPanel.askAndAwaitReply(page, TRIGGER + ': show the order again');
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(RE_PRESENTED_ANSWER, {timeout: PROVIDER_ROUND_TRIP_TIMEOUT});

    await chatPanel.continueOnDashboard(page);

    await expect(chatPanel.userMessages(page)).toHaveCount(1, {timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(RE_PRESENTED_ANSWER);
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText(RAW_DUMP_FRAGMENT);
  });
});
