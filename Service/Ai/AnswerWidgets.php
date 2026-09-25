<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Ai;

/**
 * Teaches the model the answer widgets the chat panel can render.
 *
 * The panel (view/adminhtml/web/js/mago-ui.js) turns a ```mago fenced block holding a JSON spec into
 * one of the widgets from the Mago Widget Kit. This section of the system prompt tells the model the
 * format, the types it may use and when a widget beats prose. Interactive cards (permission, progress,
 * bulk selection) are not listed: the panel builds those itself from the tool events.
 */
class AnswerWidgets
{
    /** Marker that opens the section so it is never injected twice */
    public const MARKER = '[Answer widgets]';

    /** Fence language the panel's markdown renderer looks for */
    public const FENCE = 'mago';

    /**
     * Widget types the model may emit, with the shape of their options.
     *
     * @var array<string, string>
     */
    private const CATALOG = [
        'stat' => '{"type":"stat","label":"Revenue, 7 days","value":"€38,410","delta":{"value":"12.4%",'
            . '"direction":"up|down|flat","suffix":"vs last week"}} — one figure that answers the question on '
            . 'its own.',
        'stats' => '{"type":"stats","items":[<stat>,<stat>]} — two related figures side by side, never more.',
        'sparkline' => '{"type":"sparkline","label":"Sessions","value":"24,108","points":[10,16,13,28]} '
            . '— a figure with its trend.',
        'meter' => '{"type":"meter","label":"Index queue","value":1842,"max":2400,"note":"77% processed"} — part of '
            . 'a known whole.',
        'ring' => '{"type":"ring","label":"Mobile share","percent":65,"legend":[{"label":"Mobile","value":"15,670"}'
            . ',{"label":"Desktop","value":"8,438"}]} — one ratio, never a pie of many slices.',
        'composition' => '{"type":"composition","label":"Order status, 412 total","segments":[{"label":"Complete",'
            . '"value":222},{"label":"Hold","value":54}]} — 100% split over 3–4 parts.',
        'rankedBars' => '{"type":"rankedBars","label":"Orders per category","items":[{"label":"Accessories",'
            . '"value":61},{"label":"Lighting","value":38}]} — the default for "which / how many"; '
            . 'highest first, at most 6 items.',
        'columns' => '{"type":"columns","label":"Revenue per day","points":[{"label":"Mon","value":44},'
            . '{"label":"Tue","value":58}]} — values over time, at most 14 points.',
        'lines' => '{"type":"lines","label":"Revenue, 30 days","series":[{"label":"now","points":[30,48,70]},'
            . '{"label":"previous","points":[22,34,56]}],"xLabels":["1 Aug","30 Aug"]} — this period against '
            . 'the previous one.',
        'stackedColumns' => '{"type":"stackedColumns","label":"Orders per channel","series":[{"label":"web"},'
            . '{"label":"POS"}],"points":[{"label":"wk 31","values":[41,21]}]} — total and composition per step.',
        'heatmap' => '{"type":"heatmap","label":"Orders per hour","rows":["Mon","Tue"],"values":[[1,2,3],[2,3,4]],'
            . '"scale":["8h","22h"]} — density over two dimensions.',
        'funnel' => '{"type":"funnel","label":"Checkout, 30 days","steps":[{"label":"Cart","value":6480},'
            . '{"label":"Paid","value":2334}]} — steps with their drop-off.',
        'entityList' => '{"type":"entityList","items":[{"title":"Brass Wall Sconce",'
            . '"meta":"SKU LT-2201 · 1,240 views","href":"mago://url_1"}],"more":{"count":126}} — records the '
            . 'user can open, at most 5; the rest behind "more".',
        'table' => '{"type":"table","columns":[{"key":"order","label":"Order"},{"key":"status","label":"Status"},'
            . '{"key":"total","label":"Total","align":"right"}],"rows":[{"order":"#100241",'
            . '"status":{"badge":"Hold","tone":"warn"},"total":{"text":"€248.00","strong":true}}]} — at most 3 '
            . 'columns and 5 rows; badge tones: ok, warn, danger.',
        'record' => '{"type":"record","title":"Order #100241","badge":{"text":"On hold","tone":"warn"},'
            . '"rows":[{"label":"Customer","value":"A. de Vries"},{"label":"Total","value":"€248.00",'
            . '"strong":true}]} — one entity as key-value rows.',
        'callout' => '{"type":"callout","tone":"warn|danger|ok|info","text":"Cache has not been refreshed for 6 '
            . 'days."} — a heads-up, an error or a success next to the answer.',
        'suggestions' => '{"type":"suggestions","chips":[{"label":"Split per country"},{"label":"Export CSV"}]} — '
            . 'follow-up questions the user can click; a click sends the label as their next message.',
        'paramPrompt' => '{"type":"paramPrompt","text":"Which price should it become?","prefix":"€",'
            . '"placeholder":"69.00","chips":[{"label":"−10%","value":"71.10"},{"label":"Back to 79.00",'
            . '"value":"79.00"}]} — use this, instead of a plain question, when exactly one value is missing '
            . 'before a write can run; the value the user enters or picks comes back as their next message.',
        'empty' => '{"type":"empty","title":"Nothing found","text":"Every product in Lighting has an image."} — an '
            . 'empty result, instead of a bare sentence.',
    ];

    /**
     * Which widget answers which kind of question, so the model does not have to derive it from the catalog.
     *
     * @var list<string>
     */
    private const CHOICES = [
        'a total or average for a period (revenue, order count, average order value) → "stat", or "stats" '
            . 'for two related figures',
        'a top-N or "which / how many per …" (best customers, best-selling products) → "rankedBars", or '
            . '"table" when each row needs more than one figure',
        'a list of orders, customers or products → "table", or "entityList" when every record has an admin_url',
        'one order, customer or product → "record"',
        'values over time → "columns", or "lines" to compare with the previous period',
        'nothing found → "empty"',
    ];

    /**
     * The system prompt section that explains the widgets.
     */
    public function toPromptSection(): string
    {
        $lines = [];
        $lines[] = self::MARKER . ' The chat panel renders widgets from a fenced code block with language "'
            . self::FENCE . '" whose content is one JSON object, or a JSON array of objects, each with a "type".';
        $lines[] = 'Use a widget whenever an answer carries numbers, a ranking, a trend or a list of records: '
            . 'write one short sentence with the conclusion, then the ```' . self::FENCE . ' block, then at most one '
            . 'sentence or a "suggestions" widget with follow-ups. Do not repeat the widget\'s numbers in prose '
            . 'and do not build tables, bar charts or numbered lists out of markdown or text when a widget fits. '
            . 'This also applies when a tool\'s own instructions describe its results in words: those tell you what '
            . 'the data means, the widget is how you show it.';
        $lines[] = 'Pick the widget by the question:';
        foreach (self::CHOICES as $choice) {
            $lines[] = '- ' . $choice;
        }
        $lines[] = 'Rules: only use data that a tool returned, never invent or extrapolate values; format numbers '
            . 'and currency as display strings in the user\'s locale ("€38,410", "12.4%") except where a shape asks '
            . 'for plain numbers (value, max, points, values); keep labels short and never put HTML in any field. '
            . 'Set "href" only to the admin_url a tool returned for that record (a token like mago://url_1 or a full '
            . 'url), copied exactly; never write or assemble an admin path yourself, it lacks the secret key and '
            . 'opens nothing, so leave "href" out when the tool returned none. Emit the JSON on its own lines, complete and valid, and never emit '
            . 'more than three widgets in one answer.';
        $lines[] = 'Available types and their shapes:';
        foreach (self::CATALOG as $shape) {
            $lines[] = '- ' . $shape;
        }

        return implode("\n", $lines);
    }

    /**
     * A one-line reminder that goes with a tool's instructions, so the widget guide is fresh right when
     * the model turns a tool result into an answer.
     */
    public function toToolReminder(): string
    {
        return 'When this result carries numbers, a ranking or several records, answer with a ```' . self::FENCE
            . ' widget as described in ' . self::MARKER . ', not with a markdown list or table.';
    }

    /**
     * Names of the widget types the section describes.
     *
     * @return list<string>
     */
    public function getTypes(): array
    {
        return array_keys(self::CATALOG);
    }
}
