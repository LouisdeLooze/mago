<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Api\Tool;

/**
 * Tool that carries guidance the model must see BEFORE it calls the tool.
 *
 * getInstructions() is injected just-in-time, after a call has run, so it cannot influence a
 * decision the model makes before acting — such as narrowing a "flush everything" to the one cache
 * a change touches, or asking which index the user means instead of reindexing all of them. That
 * guidance belongs here: ChatService gathers it into a single system section at the start of the
 * conversation, where it steers the first call and never reaches the human confirmation card (which
 * is built from getDescription()).
 * @api
 */
interface UpfrontGuidanceToolInterface extends ToolInterface
{
    /**
     * Guidance shown to the model before any call to this tool, empty for none
     */
    public function getUpfrontGuidance(): string;
}
