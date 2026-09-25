/**
 * Copyright © Mago Assistant
 *
 * Mago UI kit — the reusable building blocks the chat panel answers with.
 *
 * Two families, matching the design files "Mago Widget Kit" (W01–W21) and
 * "Mago Skill Actions" (S01–S14):
 *
 *   Widgets      stat, stats, sparkline, meter, ring, composition, rankedBars,
 *                columns, lines, stackedColumns, heatmap, funnel, entityList,
 *                table, record, confirmWrite, toolTrace, callout, suggestions,
 *                answerFooter, empty, skeleton
 *   Skill cards  skillAsk, skillRunning, skillLine, skillFailed, readLine,
 *                skillIrreversible, skillBulk, skillPlan, paramPrompt,
 *                undoCallout, sessionLog, skillMenu
 *
 * Every builder takes one options object and returns a detached HTMLElement,
 * so callers decide where it lands. Nothing here talks to the network; the
 * card that asks for permission only calls the onAllow / onLater callbacks it
 * was given. Styling lives in css/source/module/_components.less and relies on
 * the CSS custom properties the chat panel already defines, so the widgets
 * follow the configured accent colour.
 *
 * `render(spec)` maps a plain object ({type: 'stat', ...}) onto a builder,
 * which is how ```mago fenced blocks in an answer become widgets.
 *
 * Loaded through RequireJS as MagoAssistant_Mago/js/mago-ui; also exposed as
 * window.MagoUI for the plain-script chat panel.
 */
define([], function () {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';

    // Inline icon set (24x24, stroke = currentColor). Kept as markup strings so
    // a builder can drop one into an <svg> without a template engine.
    var ICONS = {
        check: '<path d="M5 12.5 10 17.5 19.5 7"/>',
        x: '<path d="M6 6l12 12M18 6L6 18"/>',
        xCircle: '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
        checkCircle: '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.8 2.8L16 9.5"/>',
        infoCircle: '<path d="M12 8.5v5M12 16.5h.01"/><circle cx="12" cy="12" r="9"/>',
        alert: '<path d="M12 9v4.5M12 17h.01"/><path d="M10.3 3.9 2.6 17.6A2 2 0 0 0 4.3 20.6h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
        arrowUp: '<path d="M12 19V5M6 11l6-6 6 6"/>',
        arrowDown: '<path d="M12 5v14M6 13l6 6 6-6"/>',
        arrowRight: '<path d="M5 12h13M12 5.5 18.5 12 12 18.5"/>',
        arrowUpRight: '<path d="M7 17 17 7M9 7h8v8"/>',
        chevronDown: '<path d="M6 9l6 6 6-6"/>',
        chevronUp: '<path d="M18 15l-6-6-6 6"/>',
        chevronRight: '<path d="M9 6l6 6-6 6"/>',
        copy: '<rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M15 5H6a2 2 0 0 0-2 2v9"/>',
        thumbsUp: '<path d="M7 11v9H4v-9h3Zm0 0 4-7a2.5 2.5 0 0 1 2.5 3.2L13 11h5.5a2 2 0 0 1 2 2.4l-1.2 5.2a2 2 0 0 1-2 1.4H7"/>',
        thumbsDown: '<path d="M17 13V4h3v9h-3Zm0 0-4 7a2.5 2.5 0 0 1-2.5-3.2L11 13H5.5a2 2 0 0 1-2-2.4l1.2-5.2A2 2 0 0 1 6.7 4H17"/>',
        search: '<circle cx="11" cy="11" r="7"/><path d="M16.5 16.5 21 21"/>',
        wrench: '<path d="M14.5 3.5a5 5 0 0 0 6 6L10 20a3.5 3.5 0 0 1-5-5L14.5 3.5Z"/><path d="M7.5 16.5h.01"/>',
        gridPlus: '<rect x="3" y="3" width="8" height="8" rx="2.2"/><rect x="13" y="3" width="8" height="8" rx="2.2"/><rect x="3" y="13" width="8" height="8" rx="2.2"/><path d="M14 17h6M17 14v6"/>',
        list: '<path d="M4 6h11M4 12h16M4 18h8"/>',
        barChart: '<path d="M4 19V9m5 10V5m5 14v-7m5 7V8"/>',
        imageOff: '<rect x="3" y="4" width="18" height="14" rx="2.5"/><path d="M4 17l5-4 3.5 2.5"/><path d="M3 3l18 18"/>',
        sparkle: '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 17l.7 1.8L21.5 19.5l-1.8.7L19 22l-.7-1.8-1.8-.7 1.8-.7z"/>'
    };

    // Default English copy. Override per call through opts.labels, or globally
    // through MagoUI.labels before rendering.
    var LABELS = {
        allow: 'Allow',
        always: 'Always allow',
        later: 'Not now',
        cancel: 'Cancel',
        apply: 'Apply',
        confirm: 'Confirm',
        stop: 'Stop',
        retry: 'Try again',
        details: 'Technical details',
        request: 'request',
        response: 'response',
        undo: 'Undo',
        allowPlan: 'Allow whole plan',
        pause: 'Pause',
        export: 'Export',
        session: 'This session',
        skills: 'Skills',
        menuHint: '↑↓ choose · ⏎ insert',
        risk: {read: 'read', write: 'writes', irreversible: 'irreversible'},
        showMore: 'Show %1 more',
        nothingFound: 'Nothing found',
        confirmChange: 'Confirm change',
        cannotUndo: 'Cannot be undone',
        stepOf: 'step %1 of %2',
        ofSelected: '%1 of %2'
    };

    /* ------------------------------------------------------------------ */
    /* DOM helpers                                                          */
    /* ------------------------------------------------------------------ */

    function el(tag, className, children) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        append(node, children);
        return node;
    }

    function append(node, children) {
        if (children === undefined || children === null || children === false) {
            return node;
        }
        if (Array.isArray(children)) {
            children.forEach(function (c) { append(node, c); });
        } else if (children instanceof Node) {
            node.appendChild(children);
        } else {
            node.appendChild(document.createTextNode(String(children)));
        }
        return node;
    }

    // Content can arrive as a node, as trusted HTML ({html: ...}) or as plain
    // text (anything else). Plain text is the default so a stray string from a
    // tool result cannot inject markup. While a spec from an answer is being
    // rendered (renderJson) HTML is refused altogether, because that spec was
    // written by the model.
    var allowHtml = true;

    function content(value) {
        if (value === undefined || value === null) {
            return null;
        }
        if (value instanceof Node) {
            return value;
        }
        if (typeof value === 'object' && typeof value.html === 'string') {
            if (!allowHtml) {
                return document.createTextNode(value.html.replace(/<[^>]*>/g, ''));
            }
            var wrap = el('span');
            wrap.innerHTML = value.html;
            return wrap;
        }
        if (typeof value === 'object' && typeof value.text === 'string') {
            return document.createTextNode(value.text);
        }
        return document.createTextNode(String(value));
    }

    // Links from specs may only point at web or in-admin URLs; anything else
    // (javascript:, data:) is dropped so a clickable row cannot run code.
    function safeHref(href) {
        if (typeof href !== 'string') {
            return null;
        }
        var trimmed = href.trim();
        return /^(https?:\/\/|\/|#|\?)/i.test(trimmed) ? trimmed : null;
    }

    // Colours from specs are limited to literal colours and kit tokens, so a
    // "color" cannot smuggle a url() into an inline style.
    function safeColor(color, fallback) {
        if (typeof color === 'string' && /^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]*\)|hsla?\([\d\s.,%]*\)|var\(--[\w-]+\)|[a-z]{3,20})$/i.test(color.trim())) {
            return color.trim();
        }
        return fallback;
    }

    function setHref(node, href) {
        var safe = safeHref(href);
        if (safe) {
            node.href = safe;
        }
        return !!safe;
    }

    function icon(name, size, strokeWidth) {
        var svg = document.createElementNS(SVG_NS, 'svg');
        var s = size || 16;
        svg.setAttribute('width', s);
        svg.setAttribute('height', s);
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', strokeWidth || 2.2);
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.innerHTML = ICONS[name] || '';
        return svg;
    }

    function spinner(size) {
        var s = el('span', 'mago-spin');
        if (size) {
            s.style.width = s.style.height = size + 'px';
        }
        return s;
    }

    function button(label, className, onClick) {
        var b = el('button', 'mago-btn ' + (className || ''), label);
        b.type = 'button';
        if (onClick) {
            b.addEventListener('click', onClick);
        }
        return b;
    }

    function clickable(node, handler) {
        if (!handler) {
            return node;
        }
        node.classList.add('is-clickable');
        node.setAttribute('role', 'button');
        node.tabIndex = 0;
        node.addEventListener('click', handler);
        node.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handler(e);
            }
        });
        return node;
    }

    function labels(opts) {
        var out = {};
        var key;
        for (key in LABELS) {
            if (Object.prototype.hasOwnProperty.call(LABELS, key)) {
                out[key] = LABELS[key];
            }
        }
        if (opts && opts.labels) {
            for (key in opts.labels) {
                if (Object.prototype.hasOwnProperty.call(opts.labels, key)) {
                    out[key] = opts.labels[key];
                }
            }
        }
        return out;
    }

    function tpl(text, a, b) {
        return String(text).replace('%1', a).replace('%2', b);
    }

    function locale() {
        return (document.documentElement.lang || navigator.language || 'en').replace('_', '-');
    }

    function formatNumber(n, options) {
        if (typeof n !== 'number' || !isFinite(n)) {
            return n === undefined || n === null ? '' : String(n);
        }
        try {
            return new Intl.NumberFormat(locale(), options || {maximumFractionDigits: 2}).format(n);
        } catch (e) {
            return String(n);
        }
    }

    function toneClass(tone) {
        return tone ? ' is-' + tone : '';
    }

    // Chart ramp: rank 0 is the subject (accent), then peach shades; from four
    // items on, the last one is the grey "remainder". keepRamp skips the grey
    // (a funnel's last step is still part of the story).
    var RAMP = ['var(--mago-ramp-1)', 'var(--mago-ramp-2)', 'var(--mago-ramp-3)', 'var(--mago-ramp-4)'];
    function rampColor(index, count, keepRamp) {
        if (!keepRamp && count >= 4 && index === count - 1) {
            return 'var(--mago-ramp-rest)';
        }
        return index < RAMP.length ? RAMP[index] : 'var(--mago-ramp-rest)';
    }

    function maxOf(values) {
        var m = 0;
        values.forEach(function (v) { if (typeof v === 'number' && v > m) { m = v; } });
        return m;
    }

    function pctOf(value, total) {
        return total > 0 ? Math.max(0, Math.min(100, (value / total) * 100)) : 0;
    }

    /* ------------------------------------------------------------------ */
    /* Shared atoms                                                         */
    /* ------------------------------------------------------------------ */

    // Card: the white, bordered surface every structured widget sits on.
    function card(className, children) {
        return el('div', 'mago-widget' + (className ? ' ' + className : ''), children);
    }

    function caption(text, extra) {
        return text ? el('div', 'mago-caption' + (extra ? ' ' + extra : ''), text) : null;
    }

    function num(text, className) {
        return el('span', 'mago-num' + (className ? ' ' + className : ''), text);
    }

    function badge(text, tone) {
        return el('span', 'mago-badge' + toneClass(tone), text);
    }

    function mono(text, className) {
        return el('span', 'mago-mono' + (className ? ' ' + className : ''), text);
    }

    function legendRow(items, className) {
        return el('div', 'mago-legend' + (className ? ' ' + className : ''), items.map(function (it, i) {
            var swatch = el('span', 'mago-swatch' + (it.line ? ' is-line' : ''));
            swatch.style.background = safeColor(it.color, rampColor(i, items.length));
            return el('div', 'mago-legend-item', [
                swatch,
                it.label,
                it.value !== undefined ? num(it.value, 'mago-legend-value') : null
            ]);
        }));
    }

    function delta(d) {
        if (!d) {
            return null;
        }
        var dir = d.direction || (String(d.value).charAt(0) === '-' || String(d.value).charAt(0) === '−' ? 'down' : 'up');
        var row = el('div', 'mago-delta is-' + dir);
        if (dir === 'up') {
            row.appendChild(icon('arrowUp', 13, 2.6));
        } else if (dir === 'down') {
            row.appendChild(icon('arrowDown', 13, 2.6));
        }
        row.appendChild(document.createTextNode(String(d.value)));
        if (d.suffix) {
            row.appendChild(el('span', 'mago-delta-suffix', d.suffix));
        }
        return row;
    }

    /* ------------------------------------------------------------------ */
    /* W01–W06 Metrics                                                      */
    /* ------------------------------------------------------------------ */

    // W01 Stat: one figure, one delta.
    // {label, value, delta: {value, direction: up|down|flat, suffix}}
    function stat(opts) {
        return card('mago-stat', [
            caption(opts.label),
            el('div', 'mago-stat-value mago-num', opts.value),
            delta(opts.delta),
            opts.note ? el('div', 'mago-note', opts.note) : null
        ]);
    }

    // W02 Stat pair: at most two stats side by side. {items: [statOpts, statOpts]}
    function stats(opts) {
        var items = (opts.items || []).slice(0, 2);
        return el('div', 'mago-stats', items.map(function (it) {
            var c = stat(it);
            c.classList.add('is-compact');
            return c;
        }));
    }

    function sparkPath(points, width, height, pad) {
        var max = maxOf(points);
        var min = Math.min.apply(null, points);
        var range = max - min || 1;
        var step = points.length > 1 ? (width - pad * 2) / (points.length - 1) : 0;
        return points.map(function (p, i) {
            var x = pad + i * step;
            var y = pad + (1 - (p - min) / range) * (height - pad * 2);
            return (i === 0 ? 'M' : 'L') + x.toFixed(1) + ' ' + y.toFixed(1);
        }).join(' ');
    }

    // W03 Stat + sparkline: trend without axes. {label, value, points: [n, ...]}
    function sparkline(opts) {
        var w = 150, h = 56, pad = 2;
        var points = opts.points || [];
        var line = sparkPath(points, w, h, pad);
        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('width', w);
        svg.setAttribute('height', h);
        svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
        svg.setAttribute('class', 'mago-sparkline');
        svg.setAttribute('aria-hidden', 'true');
        if (points.length > 1) {
            var last = line.substring(line.lastIndexOf('L') + 1).trim().split(' ');
            svg.innerHTML = '<path d="' + line + ' L' + (w - pad) + ' ' + (h - pad) + ' L' + pad + ' ' + (h - pad) + ' Z" class="mago-sparkline-fill"/>'
                + '<path d="' + line + '" class="mago-sparkline-line"/>'
                + '<circle cx="' + last[0] + '" cy="' + last[1] + '" r="3.4" class="mago-sparkline-dot"/>';
        }
        return card('mago-sparkstat', el('div', 'mago-sparkstat-row', [
            el('div', 'mago-sparkstat-text', [
                caption(opts.label),
                el('div', 'mago-stat-value mago-num is-compact', opts.value),
                delta(opts.delta)
            ]),
            svg
        ]));
    }

    // W04 Quota meter: part of a known whole. {label, value, max, valueText, note}
    function meter(opts) {
        var pct = pctOf(opts.value, opts.max);
        var fill = el('div', 'mago-meter-fill');
        fill.style.width = pct.toFixed(1) + '%';
        var valueText = opts.valueText || (formatNumber(opts.value) + ' / ' + formatNumber(opts.max));
        var track = el('div', 'mago-meter-track', fill);
        track.setAttribute('role', 'progressbar');
        track.setAttribute('aria-valuenow', opts.value);
        track.setAttribute('aria-valuemax', opts.max);
        return card('mago-meter', [
            el('div', 'mago-widget-head', [caption(opts.label, 'is-grow'), num(valueText, 'mago-meter-value')]),
            track,
            opts.note ? el('div', 'mago-note', opts.note) : null
        ]);
    }

    // W05 Share ring: one ratio, never a pie of six.
    // {label, percent, valueText, legend: [{label, value, color}]}
    function ring(opts) {
        var r = 34, c = 2 * Math.PI * r;
        var pct = Math.max(0, Math.min(100, opts.percent || 0));
        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('width', 88);
        svg.setAttribute('height', 88);
        svg.setAttribute('viewBox', '0 0 88 88');
        svg.setAttribute('class', 'mago-ring-svg');
        svg.setAttribute('aria-hidden', 'true');
        svg.innerHTML = '<circle cx="44" cy="44" r="' + r + '" class="mago-ring-track"/>'
            + '<circle cx="44" cy="44" r="' + r + '" class="mago-ring-value" stroke-dasharray="' + (c * pct / 100).toFixed(1) + ' ' + c.toFixed(1) + '" transform="rotate(-90 44 44)"/>'
            + '<text x="44" y="49" text-anchor="middle" class="mago-ring-text"></text>';
        // The label is caller data, so it goes in as text rather than markup.
        svg.querySelector('text').textContent = opts.valueText || Math.round(pct) + '%';
        return card('mago-ring', [
            caption(opts.label),
            el('div', 'mago-ring-row', [
                svg,
                opts.legend ? legendRow(opts.legend, 'is-stacked') : null
            ])
        ]);
    }

    // W06 Composition bar: 100% split over 3–4 parts.
    // {label, segments: [{label, value, color}]}
    function composition(opts) {
        var segs = opts.segments || [];
        var total = segs.reduce(function (s, x) { return s + (x.value || 0); }, 0);
        var bar = el('div', 'mago-composition-bar', segs.map(function (s, i) {
            var part = el('div', 'mago-composition-seg');
            part.style.width = pctOf(s.value, total).toFixed(2) + '%';
            part.style.background = safeColor(s.color, rampColor(i, segs.length));
            part.title = s.label + ' ' + formatNumber(s.value);
            return part;
        }));
        return card('mago-composition', [
            caption(opts.label),
            bar,
            legendRow(segs.map(function (s, i) {
                return {label: s.label, value: s.valueText || formatNumber(s.value), color: safeColor(s.color, rampColor(i, segs.length))};
            }), 'is-wrap')
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* W07–W12 Charts                                                       */
    /* ------------------------------------------------------------------ */

    // W07 Ranked bars: the default for "which / how many".
    // {label, items: [{label, value, valueText}]}
    function rankedBars(opts) {
        var items = opts.items || [];
        var max = maxOf(items.map(function (i) { return i.value; }));
        return card('mago-ranked', [
            caption(opts.label),
            el('div', 'mago-ranked-rows', items.map(function (it, i) {
                var fill = el('div', 'mago-bar-fill');
                fill.style.width = pctOf(it.value, max).toFixed(1) + '%';
                fill.style.background = safeColor(it.color, rampColor(i, items.length));
                var row = el('div', 'mago-ranked-row', [
                    el('div', 'mago-ranked-label', it.label),
                    el('div', 'mago-bar-track', fill),
                    num(it.valueText || formatNumber(it.value), 'mago-ranked-value')
                ]);
                return clickable(row, it.onClick);
            }))
        ]);
    }

    // W08 Columns over time: ≤ 14 points, the peak in accent.
    // {label, points: [{label, value, highlight}]}
    function columns(opts) {
        var points = opts.points || [];
        var max = maxOf(points.map(function (p) { return p.value; }));
        var hasExplicit = points.some(function (p) { return p.highlight; });
        return card('mago-columns', [
            caption(opts.label),
            el('div', 'mago-columns-plot', points.map(function (p) {
                var isPeak = hasExplicit ? !!p.highlight : (p.value === max && max > 0);
                var col = el('div', 'mago-column' + (isPeak ? ' is-peak' : ''));
                col.style.height = pctOf(p.value, max).toFixed(1) + '%';
                col.title = p.label + ' ' + (p.valueText || formatNumber(p.value));
                return col;
            })),
            el('div', 'mago-axis', points.map(function (p) {
                var isPeak = hasExplicit ? !!p.highlight : (p.value === max && max > 0);
                return el('div', 'mago-axis-label' + (isPeak ? ' is-peak' : ''), p.label);
            }))
        ]);
    }

    // W09 Two-series lines: this period vs the previous one.
    // {label, series: [{label, points, style: 'solid'|'dashed'}], xLabels: [...]}
    function lines(opts) {
        var w = 360, h = 118, pad = 4;
        var series = opts.series || [];
        var all = [];
        series.forEach(function (s) { all = all.concat(s.points || []); });
        var max = maxOf(all);
        var min = Math.min.apply(null, all.length ? all : [0]);
        var range = max - min || 1;
        function path(points) {
            var step = points.length > 1 ? (w - pad * 2) / (points.length - 1) : 0;
            return points.map(function (p, i) {
                var x = pad + i * step;
                var y = 8 + (1 - (p - min) / range) * (h - 8 - 8);
                return (i === 0 ? 'M' : 'L') + x.toFixed(1) + ' ' + y.toFixed(1);
            }).join(' ');
        }
        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
        svg.setAttribute('preserveAspectRatio', 'none');
        svg.setAttribute('class', 'mago-lines-svg');
        svg.setAttribute('aria-hidden', 'true');
        var markup = '<line x1="0" y1="8" x2="360" y2="8" class="mago-grid"/>'
            + '<line x1="0" y1="46" x2="360" y2="46" class="mago-grid"/>'
            + '<line x1="0" y1="84" x2="360" y2="84" class="mago-grid"/>';
        // Paint order: the primary fill first, then the comparison lines, then
        // the primary line on top, so a lower "previous period" stays visible.
        var primary = series[0] && series[0].points && series[0].points.length > 1 ? path(series[0].points) : null;
        if (primary) {
            markup += '<path d="' + primary + ' L' + (w - pad) + ' ' + (h - 8) + ' L' + pad + ' ' + (h - 8) + ' Z" class="mago-line-fill"/>';
        }
        series.slice(1).forEach(function (s) {
            markup += '<path d="' + path(s.points || []) + '" class="mago-line is-secondary' + (s.style === 'solid' ? '' : ' is-dashed') + '"/>';
        });
        if (primary) {
            markup += '<path d="' + primary + '" class="mago-line is-primary"/>';
        }
        svg.innerHTML = markup;
        return card('mago-lines', [
            el('div', 'mago-widget-head', [
                caption(opts.label, 'is-grow'),
                series.length > 1 ? legendRow(series.map(function (s, i) {
                    return {label: s.label, line: true, color: i === 0 ? 'var(--mago-ramp-1)' : 'var(--mago-line-secondary)'};
                }), 'is-inline') : null
            ]),
            svg,
            opts.xLabels ? el('div', 'mago-axis is-spread', opts.xLabels.map(function (l) { return el('span', 'mago-axis-label', l); })) : null
        ]);
    }

    // W10 Stacked columns: total and composition per step.
    // {label, series: [{label, color}], points: [{label, values: [...]}]}
    function stackedColumns(opts) {
        var series = opts.series || [];
        var points = opts.points || [];
        var totals = points.map(function (p) { return (p.values || []).reduce(function (s, v) { return s + v; }, 0); });
        var max = maxOf(totals);
        var colors = series.map(function (s, i) { return safeColor(s.color, i === 0 ? 'var(--mago-ramp-1)' : 'var(--mago-ramp-3)'); });
        return card('mago-stacked', [
            el('div', 'mago-widget-head', [
                caption(opts.label, 'is-grow'),
                legendRow(series.map(function (s, i) { return {label: s.label, color: colors[i]}; }), 'is-inline')
            ]),
            el('div', 'mago-columns-plot is-stacked', points.map(function (p, pi) {
                var total = totals[pi] || 0;
                var col = el('div', 'mago-stack');
                col.style.height = pctOf(total, max).toFixed(1) + '%';
                // Top of the stack is the last series, so draw in reverse.
                (p.values || []).slice().reverse().forEach(function (v, ri) {
                    var si = p.values.length - 1 - ri;
                    var seg = el('div', 'mago-stack-seg');
                    seg.style.height = pctOf(v, total).toFixed(1) + '%';
                    seg.style.background = colors[si];
                    seg.title = (series[si] ? series[si].label + ' ' : '') + formatNumber(v);
                    col.appendChild(seg);
                });
                return col;
            })),
            el('div', 'mago-axis', points.map(function (p) { return el('div', 'mago-axis-label', p.label); }))
        ]);
    }

    // W11 Heatmap: two dimensions, rough density.
    // {label, rows: ['mo', ...], values: [[...], ...], scale: ['8h', '22h']}
    function heatmap(opts) {
        var rows = opts.rows || [];
        var values = opts.values || [];
        var flat = [];
        values.forEach(function (r) { flat = flat.concat(r); });
        var max = maxOf(flat);
        var cols = values.length ? maxOf(values.map(function (r) { return r.length; })) : 0;
        var grid = el('div', 'mago-heat-grid');
        grid.style.gridTemplateColumns = 'repeat(' + cols + ', 1fr)';
        values.forEach(function (r) {
            r.forEach(function (v) {
                var cell = el('div', 'mago-heat-cell');
                var level = max > 0 ? v / max : 0;
                cell.style.background = level <= 0 ? 'var(--mago-surface)'
                    : level < 0.2 ? 'var(--mago-ramp-5)'
                    : level < 0.4 ? 'var(--mago-ramp-4)'
                    : level < 0.6 ? 'var(--mago-ramp-3)'
                    : level < 0.8 ? 'var(--mago-ramp-2)'
                    : 'var(--mago-ramp-1)';
                cell.title = formatNumber(v);
                grid.appendChild(cell);
            });
        });
        return card('mago-heatmap', [
            caption(opts.label),
            el('div', 'mago-heat-row', [
                el('div', 'mago-heat-rows', rows.map(function (r) { return el('div', 'mago-heat-rowlabel', r); })),
                grid
            ]),
            opts.scale ? el('div', 'mago-heat-scale', [opts.scale[0], el('span', 'mago-heat-gradient'), opts.scale[1]]) : null
        ]);
    }

    // W12 Funnel: steps with the drop-off beside them.
    // {label, steps: [{label, value, valueText}]}
    function funnel(opts) {
        var steps = opts.steps || [];
        var max = steps.length ? steps[0].value : 0;
        return card('mago-funnel', [
            caption(opts.label),
            el('div', 'mago-funnel-steps', steps.map(function (s, i) {
                var bar = el('div', 'mago-funnel-bar' + (i >= 2 ? ' is-light' : ''), [
                    s.label,
                    num(s.valueText || formatNumber(s.value), 'mago-funnel-value')
                ]);
                bar.style.width = pctOf(s.value, max).toFixed(1) + '%';
                bar.style.background = rampColor(i, steps.length, true);
                var drop = i > 0 && steps[i - 1].value > 0
                    ? num('−' + Math.round((1 - s.value / steps[i - 1].value) * 100) + '%', 'mago-funnel-drop')
                    : null;
                return el('div', 'mago-funnel-step', [bar, drop]);
            }))
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* W13–W15 Data                                                         */
    /* ------------------------------------------------------------------ */

    // W13 Entity list: thumb, two lines, one action. Never more than five in the panel.
    // {items: [{title, meta, thumb, href, onClick, action: {label, href, onClick}}], more: {count, label, onClick, href}}
    function entityList(opts) {
        var items = opts.items || [];
        var list = el('div', 'mago-entities', items.map(function (it) {
            var thumb;
            if (safeHref(it.thumb)) {
                thumb = el('img', 'mago-entity-thumb');
                thumb.src = safeHref(it.thumb);
                thumb.alt = '';
            } else {
                thumb = el('div', 'mago-entity-thumb is-empty', icon('imageOff', 18, 2));
            }
            var action = null;
            if (it.action) {
                var actionHref = safeHref(it.action.href);
                action = el(actionHref ? 'a' : 'span', 'mago-entity-action', it.action.label);
                if (actionHref) {
                    action.href = actionHref;
                }
                if (it.action.onClick) {
                    action.addEventListener('click', function (e) { e.stopPropagation(); it.action.onClick(e, it); });
                }
            }
            var rowHref = safeHref(it.href);
            var row = el(rowHref ? 'a' : 'div', 'mago-entity', [
                thumb,
                el('div', 'mago-entity-text', [
                    el('div', 'mago-entity-title', it.title),
                    it.meta ? el('div', 'mago-entity-meta mago-num', it.meta) : null
                ]),
                action
            ]);
            if (rowHref) {
                row.href = rowHref;
            }
            return clickable(row, it.onClick ? function (e) { it.onClick(e, it); } : null);
        }));
        if (opts.more) {
            var moreHref = safeHref(opts.more.href);
            var more = el(moreHref ? 'a' : 'div', 'mago-entity-more', [
                opts.more.label || tpl(labels(opts).showMore, opts.more.count),
                icon('chevronDown', 14, 2.2)
            ]);
            if (moreHref) {
                more.href = moreHref;
            }
            list.appendChild(clickable(more, opts.more.onClick));
        }
        return list;
    }

    // W14 Compact table: max three columns at panel width.
    // {columns: [{key, label, align, width}], rows: [{key: value | {text, badge, tone, strong}}], onRowClick}
    function table(opts) {
        var cols = opts.columns || [];
        var template = cols.map(function (c) { return c.width || '1fr'; }).join(' ');
        function cell(col, value) {
            var d = el('div', 'mago-table-cell' + (col.align === 'right' ? ' is-right' : ''));
            if (value && typeof value === 'object' && !(value instanceof Node)) {
                if (value.badge) {
                    d.appendChild(badge(value.badge, value.tone));
                } else {
                    d.appendChild(content(value.text));
                }
                if (value.strong) {
                    d.classList.add('is-strong');
                }
                if (value.num) {
                    d.classList.add('mago-num');
                }
            } else {
                d.appendChild(content(value));
                if (col.num) {
                    d.classList.add('mago-num');
                }
            }
            return d;
        }
        var head = el('div', 'mago-table-row is-head', cols.map(function (c) {
            return el('div', 'mago-table-cell' + (c.align === 'right' ? ' is-right' : ''), c.label);
        }));
        head.style.gridTemplateColumns = template;
        var rows = (opts.rows || []).map(function (r) {
            var row = el('div', 'mago-table-row', cols.map(function (c) { return cell(c, r[c.key]); }));
            row.style.gridTemplateColumns = template;
            return clickable(row, opts.onRowClick ? function (e) { opts.onRowClick(r, e); } : null);
        });
        return el('div', 'mago-widget mago-table is-flush', [head].concat(rows));
    }

    // W15 Record card: one entity, key-value rows.
    // {title, badge: {text, tone}, rows: [{label, value, strong, num}]}
    function record(opts) {
        return el('div', 'mago-widget mago-record is-flush', [
            el('div', 'mago-record-head', [
                el('div', 'mago-record-title', opts.title),
                opts.badge ? badge(opts.badge.text, opts.badge.tone) : null
            ]),
            el('div', 'mago-record-rows', (opts.rows || []).map(function (r) {
                return el('div', 'mago-kv', [
                    el('div', 'mago-kv-label', r.label),
                    el('div', 'mago-kv-value' + (r.strong ? ' is-strong' : '') + (r.num ? ' mago-num' : ''), content(r.value))
                ]);
            }))
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* W16–W21 Action & state                                               */
    /* ------------------------------------------------------------------ */

    // W16 Confirm write: diff plus explicit confirmation.
    // {title, text, diff: {from, to, delta}, onConfirm, onCancel}
    function confirmWrite(opts) {
        var L = labels(opts);
        var diff = opts.diff ? el('div', 'mago-diff', [
            num(opts.diff.from, 'mago-diff-from'),
            icon('arrowRight', 16, 2.2),
            num(opts.diff.to, 'mago-diff-to'),
            opts.diff.delta ? num(opts.diff.delta, 'mago-diff-delta') : null
        ]) : null;
        return el('div', 'mago-widget mago-ask is-accent', [
            el('div', 'mago-ask-eyebrow', [icon('infoCircle', 15), opts.title || L.confirmChange]),
            el('div', 'mago-ask-text', content(opts.text)),
            diff,
            el('div', 'mago-actions', [
                button(opts.confirmLabel || L.apply, 'is-primary', opts.onConfirm),
                button(opts.cancelLabel || L.cancel, 'is-secondary', opts.onCancel)
            ])
        ]);
    }

    // W17 Tool trace: what Mago did, in plain words.
    // {steps: [{label, state: 'done'|'active'|'pending'|'failed', tool}]}
    function traceStep(step) {
        var state = step.state || 'done';
        var lead;
        if (state === 'active') {
            lead = spinner(14);
        } else if (state === 'pending') {
            lead = el('span', 'mago-trace-dot');
        } else if (state === 'failed') {
            lead = el('span', 'mago-trace-icon is-danger', icon('xCircle', 16, 2.4));
        } else {
            lead = el('span', 'mago-trace-icon is-ok', icon('check', 16, 2.4));
        }
        var row = el('div', 'mago-trace-step is-' + state, [
            lead,
            el('span', 'mago-trace-label', step.label),
            step.tool ? mono(step.tool, 'mago-trace-tool') : null
        ]);
        row.magoSetState = function (next, label) {
            var fresh = traceStep({state: next, label: label || step.label, tool: step.tool});
            row.className = fresh.className;
            row.innerHTML = '';
            while (fresh.firstChild) {
                row.appendChild(fresh.firstChild);
            }
        };
        return row;
    }

    function toolTrace(opts) {
        return el('div', 'mago-trace', (opts.steps || []).map(traceStep));
    }

    // W18 Callouts: heads-up, error, success.
    // {tone: 'warn'|'danger'|'ok'|'info', text, action: {label, href, onClick}}
    function callout(opts) {
        var tone = opts.tone || 'info';
        var iconName = tone === 'ok' ? 'checkCircle' : tone === 'danger' ? 'xCircle' : tone === 'warn' ? 'alert' : 'infoCircle';
        var body = el('div', 'mago-callout-text', content(opts.text));
        if (opts.action) {
            var a = el(safeHref(opts.action.href) ? 'a' : 'span', 'mago-callout-link', opts.action.label);
            setHref(a, opts.action.href);
            if (opts.action.onClick) {
                a.addEventListener('click', opts.action.onClick);
            }
            body.appendChild(document.createTextNode(' '));
            body.appendChild(a);
        }
        var node = el('div', 'mago-callout is-' + tone, [
            el('span', 'mago-callout-icon', icon(iconName, 17, tone === 'ok' ? 2.4 : 2.2)),
            body
        ]);
        node.setAttribute('role', tone === 'danger' ? 'alert' : 'status');
        return node;
    }

    // W19 Suggestion cards and follow-up chips.
    // {cards: [{label, icon, onClick, href}], chips: [{label, onClick}]}
    function suggestionCard(opts) {
        var node = el(safeHref(opts.href) ? 'a' : 'div', 'mago-suggestion', [
            el('span', 'mago-suggestion-icon', icon(opts.icon || 'sparkle', 19, 2)),
            el('span', 'mago-suggestion-label', opts.label),
            icon('chevronRight', 16, 2.2)
        ]);
        setHref(node, opts.href);
        return clickable(node, opts.onClick);
    }

    function chips(items) {
        return el('div', 'mago-chips', (items || []).map(function (c) {
            var chip = el('button', 'mago-chip', c.label);
            chip.type = 'button';
            if (c.onClick) {
                chip.addEventListener('click', function (e) { c.onClick(e, c); });
            }
            return chip;
        }));
    }

    function suggestions(opts) {
        return el('div', 'mago-suggestions', [
            el('div', 'mago-suggestion-cards', (opts.cards || []).map(suggestionCard)),
            opts.chips && opts.chips.length ? chips(opts.chips) : null
        ]);
    }

    // W20 Answer footer: primary exit plus feedback.
    // {primary: {label, href, onClick, external}, onCopy, onFeedback(vote)}
    function answerFooter(opts) {
        var primary = null;
        if (opts.primary) {
            var primaryHref = safeHref(opts.primary.href);
            primary = el(primaryHref ? 'a' : 'button', 'mago-btn is-ink', [
                opts.primary.label,
                icon(opts.primary.external === false ? 'arrowRight' : 'arrowUpRight', 14, 2.2)
            ]);
            if (primaryHref) {
                primary.href = primaryHref;
                if (opts.primary.external) {
                    primary.target = '_blank';
                    primary.rel = 'noopener';
                }
            } else {
                primary.type = 'button';
            }
            if (opts.primary.onClick) {
                primary.addEventListener('click', opts.primary.onClick);
            }
        }
        function iconBtn(name, title, handler) {
            var b = el('button', 'mago-icon-btn', icon(name, 15, 2));
            b.type = 'button';
            b.title = title;
            b.setAttribute('aria-label', title);
            if (handler) {
                b.addEventListener('click', function () {
                    handler();
                    b.classList.add('is-active');
                });
            }
            return b;
        }
        var tools = el('div', 'mago-footer-tools', [
            opts.onCopy ? iconBtn('copy', opts.copyLabel || 'Copy', opts.onCopy) : null,
            opts.onFeedback ? iconBtn('thumbsUp', opts.upLabel || 'Helpful', function () { opts.onFeedback('up'); }) : null,
            opts.onFeedback ? iconBtn('thumbsDown', opts.downLabel || 'Not helpful', function () { opts.onFeedback('down'); }) : null
        ]);
        return el('div', 'mago-footer', [primary, tools]);
    }

    // W21 Empty state and loading skeleton.
    function empty(opts) {
        return el('div', 'mago-empty', [
            el('span', 'mago-empty-icon', icon(opts.icon || 'search', 22, 2)),
            el('div', 'mago-empty-title', opts.title || labels(opts).nothingFound),
            opts.text ? el('div', 'mago-empty-text', content(opts.text)) : null
        ]);
    }

    function skeleton(opts) {
        var widths = (opts && opts.widths) || ['38%', '88%', '64%'];
        var node = card('mago-skeleton', widths.map(function (w) {
            var line = el('div', 'mago-skeleton-line');
            line.style.width = w;
            return line;
        }));
        node.setAttribute('aria-busy', 'true');
        return node;
    }

    /* ------------------------------------------------------------------ */
    /* Skill actions S01–S12                                                */
    /* ------------------------------------------------------------------ */

    function skillHead(opts, tone, leadIcon) {
        return el('div', 'mago-skill-head', [
            leadIcon,
            el('div', 'mago-skill-title', opts.title),
            opts.tool ? mono(opts.tool, 'mago-skill-tool') : null
        ]);
    }

    // Parameter rows: {key, value, mono: bool, muted: string}
    function paramTable(params) {
        if (!params || !params.length) {
            return null;
        }
        return el('div', 'mago-params', params.map(function (p) {
            var value;
            if (p.mono === false) {
                value = el('div', 'mago-param-value', [content(p.value), p.muted ? el('span', 'mago-param-muted', ' ' + p.muted) : null]);
            } else {
                value = el('div', 'mago-param-value', mono(p.value, 'is-chip'));
            }
            return el('div', 'mago-param', [el('div', 'mago-param-key', p.key), value]);
        }));
    }

    // Convert a tool input object into parameter rows. Nested values are shown as JSON.
    function paramsFromInput(input) {
        var out = [];
        Object.keys(input || {}).forEach(function (k) {
            var v = input[k];
            if (v === null || v === undefined || v === '') {
                out.push({key: k, value: '—', mono: false});
            } else if (typeof v === 'object') {
                out.push({key: k, value: JSON.stringify(v)});
            } else {
                out.push({key: k, value: String(v)});
            }
        });
        return out;
    }

    // S01 Asks permission: a write action with its parameters and Allow / Always / Not now.
    // {title, tool, text, params, onAllow, onAlways, onLater, classes: {allow, later}}
    function skillAsk(opts) {
        var L = labels(opts);
        var classes = opts.classes || {};
        var actions = el('div', 'mago-actions' + (classes.actions ? ' ' + classes.actions : ''), [
            button(opts.allowLabel || L.allow, 'is-primary' + (classes.allow ? ' ' + classes.allow : ''), opts.onAllow),
            opts.onAlways ? button(L.always, 'is-secondary', opts.onAlways) : null,
            button(opts.laterLabel || L.later, 'is-ghost' + (classes.later ? ' ' + classes.later : ''), opts.onLater)
        ]);
        return el('div', 'mago-widget mago-skill is-accent is-flush', [
            skillHead(opts, 'accent', el('span', 'mago-skill-icon', icon(opts.icon || 'wrench', 16, 2.1))),
            el('div', 'mago-skill-body', [
                el('div', 'mago-ask-text', content(opts.text)),
                paramTable(opts.params),
                actions
            ])
        ]);
    }

    // S02 Busy: spinner, elapsed, stop, pulsing progress and the steps so far.
    // {title, elapsed, progress (0–100), steps: [{label, state}], onStop}
    // The returned element exposes magoUpdate({progress, elapsed, steps}) and magoAddStep(step).
    function skillRunning(opts) {
        var L = labels(opts);
        var elapsed = el('span', 'mago-skill-meta mago-num', opts.elapsed || '');
        var fill = el('div', 'mago-progress-fill is-pulse');
        fill.style.width = (opts.progress === undefined ? 64 : opts.progress) + '%';
        var steps = el('div', 'mago-trace is-tight', (opts.steps || []).map(traceStep));
        var stop = opts.onStop ? button(L.stop, 'is-link', opts.onStop) : null;
        var node = el('div', 'mago-widget mago-skill is-running is-flush', [
            el('div', 'mago-skill-head', [spinner(15), el('div', 'mago-skill-title', opts.title), elapsed, stop]),
            el('div', 'mago-skill-body', [el('div', 'mago-progress', fill), steps])
        ]);
        node.setAttribute('aria-busy', 'true');
        node.magoUpdate = function (next) {
            if (next.progress !== undefined) {
                fill.style.width = next.progress + '%';
            }
            if (next.elapsed !== undefined) {
                elapsed.textContent = next.elapsed;
            }
            if (next.steps) {
                steps.innerHTML = '';
                next.steps.forEach(function (s) { steps.appendChild(traceStep(s)); });
            }
        };
        node.magoAddStep = function (step) {
            var row = traceStep(step);
            steps.appendChild(row);
            return row;
        };
        return node;
    }

    // Payloads print on one line with breathing room, like the design: { "a": 1, "b": 2 }
    function payloadText(value) {
        if (typeof value === 'string') {
            return value;
        }
        try {
            return JSON.stringify(value, null, 1).replace(/\n\s*/g, ' ');
        } catch (e) {
            return String(value);
        }
    }

    // S03 / S05 Done: the collapsed line under the result; click to expand payload and response.
    // {title, action, duration, state: 'done'|'failed'|'skipped', request, response, expanded, onToggle}
    function skillLine(opts) {
        var L = labels(opts);
        var state = opts.state || 'done';
        var expandable = opts.request !== undefined || opts.response !== undefined;
        var chevron = expandable ? el('span', 'mago-line-chevron', icon(opts.expanded ? 'chevronUp' : 'chevronDown', 15, 2.2)) : null;
        var lead = state === 'failed' ? el('span', 'mago-trace-icon is-danger', icon('xCircle', 15, 2.6))
            : state === 'skipped' ? el('span', 'mago-trace-icon is-muted', icon('x', 15, 2.6))
            : el('span', 'mago-trace-icon is-ok', icon('check', 15, 2.6));
        var head = el('div', 'mago-line-head', [
            lead,
            el('div', 'mago-line-text', [
                opts.title,
                opts.action ? el('span', 'mago-line-sep', ' · ') : null,
                opts.action ? mono(opts.action, 'mago-line-action') : null
            ]),
            opts.duration ? num(opts.duration, 'mago-line-meta') : null,
            chevron
        ]);
        var details = null;
        if (expandable) {
            details = el('div', 'mago-line-details', [
                opts.request !== undefined ? el('div', 'mago-payload', [el('span', 'mago-payload-label', L.request), el('br'), payloadText(opts.request)]) : null,
                opts.response !== undefined ? el('div', 'mago-payload', [el('span', 'mago-payload-label', L.response), el('br'), payloadText(opts.response)]) : null
            ]);
        }
        var node = el('div', 'mago-line is-' + state + (opts.expanded ? ' is-expanded' : '') + (expandable ? ' is-expandable' : ''), [head, details]);
        if (expandable) {
            clickable(head, function () {
                var open = node.classList.toggle('is-expanded');
                chevron.innerHTML = '';
                chevron.appendChild(icon(open ? 'chevronUp' : 'chevronDown', 15, 2.2));
                head.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (opts.onToggle) {
                    opts.onToggle(open);
                }
            });
            head.setAttribute('aria-expanded', opts.expanded ? 'true' : 'false');
        }
        return node;
    }

    // S04 Failed: the error in plain words, with retry and technical details.
    // {title, code, text, details, onRetry, onDetails}
    function skillFailed(opts) {
        var L = labels(opts);
        var details = opts.details ? el('pre', 'mago-skill-details', typeof opts.details === 'string' ? opts.details : JSON.stringify(opts.details, null, 2)) : null;
        if (details) {
            details.hidden = true;
        }
        var node = el('div', 'mago-widget mago-skill is-danger is-flush', [
            skillHead(opts, 'danger', el('span', 'mago-skill-icon', icon('xCircle', 16, 2.2))),
            el('div', 'mago-skill-body', [
                el('div', 'mago-ask-text', content(opts.text)),
                el('div', 'mago-actions is-tight', [
                    opts.onRetry ? button(L.retry, 'is-outline-danger', opts.onRetry) : null,
                    (details || opts.onDetails) ? button(L.details, 'is-link-danger', function (e) {
                        if (details) {
                            details.hidden = !details.hidden;
                        }
                        if (opts.onDetails) {
                            opts.onDetails(e);
                        }
                    }) : null
                ]),
                details
            ])
        ]);
        node.setAttribute('role', 'alert');
        // The mono badge in the head carries the error code rather than the tool name.
        if (opts.code) {
            var head = node.querySelector('.mago-skill-head');
            var existing = head.querySelector('.mago-skill-tool');
            if (existing) {
                existing.remove();
            }
            head.appendChild(mono(opts.code, 'mago-skill-tool'));
        }
        return node;
    }

    // S06 Read, no question: one quiet line per read-only call.
    // {text, tool, action, state: 'done'|'active'}
    function readLine(opts) {
        var step = traceStep({
            label: opts.text,
            state: opts.state || 'done',
            tool: opts.tool ? opts.tool + (opts.action ? ' · ' + opts.action : '') : null
        });
        step.classList.add('mago-readline');
        return step;
    }

    // S07 Irreversible: impact list and a ticked acknowledgement before the button arms.
    // {title, tool, text, params, impacts: [...], ackLabel, confirmLabel, onConfirm, onCancel, classes: {actions, confirm, cancel}}
    function skillIrreversible(opts) {
        var L = labels(opts);
        var classes = opts.classes || {};
        var checkbox = el('input', 'mago-check-input');
        checkbox.type = 'checkbox';
        var ack = el('label', 'mago-check', [checkbox, el('span', 'mago-check-box', icon('check', 12, 3.2)), el('span', 'mago-check-label', opts.ackLabel || 'I understand this cannot be undone')]);
        var confirm = button(opts.confirmLabel || L.confirm, 'is-danger' + (classes.confirm ? ' ' + classes.confirm : ''), function (e) {
            if (!checkbox.checked) {
                return;
            }
            if (opts.onConfirm) {
                opts.onConfirm(e);
            }
        });
        confirm.disabled = true;
        checkbox.addEventListener('change', function () {
            confirm.disabled = !checkbox.checked;
        });
        var cancel = button(opts.cancelLabel || L.cancel, 'is-outline-danger' + (classes.cancel ? ' ' + classes.cancel : ''), opts.onCancel);
        return el('div', 'mago-widget mago-skill is-danger is-flush', [
            skillHead({title: opts.title || L.cannotUndo, tool: opts.tool}, 'danger', el('span', 'mago-skill-icon', icon('alert', 16, 2.2))),
            el('div', 'mago-skill-body', [
                el('div', 'mago-ask-text', content(opts.text)),
                paramTable(opts.params),
                opts.impacts && opts.impacts.length ? el('div', 'mago-impacts', opts.impacts.map(function (i) {
                    return el('div', 'mago-impact', [el('span', 'mago-impact-mark', '−'), content(i)]);
                })) : null,
                ack,
                el('div', 'mago-actions is-tight' + (classes.actions ? ' ' + classes.actions : ''), [confirm, cancel])
            ])
        ]);
    }

    // S08 Bulk with selection: tick the records to act on, the button counts them.
    // {title, icon, text, items: [{id, label, meta, checked, disabled}], confirmLabel(n), onConfirm(selectedIds), onLater,
    //  classes: {actions, confirm, later}}
    function skillBulk(opts) {
        var L = labels(opts);
        var classes = opts.classes || {};
        var items = opts.items || [];
        var state = items.map(function (it) { return it.checked !== false && !it.disabled; });
        var counter = num('', 'mago-skill-count');
        var confirm = button('', 'is-primary' + (classes.confirm ? ' ' + classes.confirm : ''), function (e) {
            var selected = items.filter(function (it, i) { return state[i]; });
            if (opts.onConfirm) {
                opts.onConfirm(selected.map(function (it) { return it.id !== undefined ? it.id : it.label; }), selected, e);
            }
        });
        function sync() {
            var n = state.filter(Boolean).length;
            counter.textContent = tpl(L.ofSelected, n, items.length);
            confirm.textContent = typeof opts.confirmLabel === 'function' ? opts.confirmLabel(n) : (opts.confirmLabel || L.confirm) + (n ? ' (' + n + ')' : '');
            confirm.disabled = n === 0;
        }
        var rows = items.map(function (it, i) {
            var box = el('span', 'mago-check-box', icon('check', 11, 3.2));
            var row = el('div', 'mago-bulk-row' + (it.disabled ? ' is-disabled' : ''), [
                box,
                el('span', 'mago-bulk-label mago-num', it.label),
                it.meta ? num(it.meta, 'mago-bulk-meta') : null
            ]);
            function paint() {
                row.classList.toggle('is-checked', !!state[i]);
                row.setAttribute('aria-checked', state[i] ? 'true' : 'false');
            }
            row.setAttribute('role', 'checkbox');
            paint();
            if (!it.disabled) {
                clickable(row, function () {
                    state[i] = !state[i];
                    paint();
                    sync();
                });
            }
            return row;
        });
        sync();
        return el('div', 'mago-widget mago-skill is-accent is-flush', [
            el('div', 'mago-skill-head', [
                el('span', 'mago-skill-icon', icon(opts.icon || 'gridPlus', 16, 2.1)),
                el('div', 'mago-skill-title', opts.title),
                counter
            ]),
            el('div', 'mago-skill-body is-list', [
                opts.text ? el('div', 'mago-ask-text is-lead', content(opts.text)) : null,
                opts.notice || null,
                el('div', 'mago-bulk-rows', rows),
                el('div', 'mago-actions is-tight' + (classes.actions ? ' ' + classes.actions : ''), [
                    confirm,
                    button(opts.laterLabel || L.later, 'is-ghost' + (classes.later ? ' ' + classes.later : ''), opts.onLater)
                ])
            ])
        ]);
    }

    // S09 Plan with several steps: a timeline whose write steps ask later.
    // {title, current, total, steps: [{title, meta, state: 'done'|'active'|'ask'|'pending'}], onAllowAll, onPause}
    function skillPlan(opts) {
        var L = labels(opts);
        var steps = opts.steps || [];
        return el('div', 'mago-widget mago-skill is-flush', [
            el('div', 'mago-skill-head', [
                el('span', 'mago-skill-icon', icon('list', 16, 2.1)),
                el('div', 'mago-skill-title', opts.title),
                opts.total ? num(tpl(L.stepOf, opts.current || 1, opts.total), 'mago-skill-meta') : null
            ]),
            el('div', 'mago-skill-body', [
                el('div', 'mago-plan', steps.map(function (s, i) {
                    var state = s.state || 'pending';
                    var marker;
                    if (state === 'done') {
                        marker = el('span', 'mago-plan-marker is-done', icon('check', 12, 3));
                    } else if (state === 'active') {
                        marker = spinner(20);
                        marker.classList.add('mago-plan-marker');
                    } else {
                        marker = el('span', 'mago-plan-marker is-' + state);
                    }
                    return el('div', 'mago-plan-step is-' + state, [
                        el('div', 'mago-plan-rail', [marker, i < steps.length - 1 ? el('span', 'mago-plan-line') : null]),
                        el('div', 'mago-plan-text', [
                            el('div', 'mago-plan-title', s.title),
                            s.meta ? el('div', 'mago-plan-meta' + (state === 'ask' ? ' is-accent' : ''), s.meta) : null
                        ])
                    ]);
                })),
                (opts.onAllowAll || opts.onPause) ? el('div', 'mago-actions is-divided', [
                    opts.onAllowAll ? button(L.allowPlan, 'is-secondary', opts.onAllowAll) : null,
                    opts.onPause ? button(L.pause, 'is-ghost is-danger-hover', opts.onPause) : null
                ]) : null
            ])
        ]);
    }

    // S10 Missing parameter: the question, an inline field and a few quick picks.
    // {text, prefix, value, placeholder, type, submitLabel, onSubmit(value), chips: [{label, value}]}
    function paramPrompt(opts) {
        var L = labels(opts);
        var input = el('input', 'mago-field-input');
        input.type = opts.type || 'text';
        input.value = opts.value || '';
        input.placeholder = opts.placeholder || '';
        function submit() {
            if (opts.onSubmit) {
                opts.onSubmit(input.value);
            }
        }
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });
        var field = el('label', 'mago-field', [opts.prefix ? el('span', 'mago-field-prefix', opts.prefix) : null, input]);
        var node = el('div', 'mago-widget mago-ask is-accent', [
            el('div', 'mago-ask-text', content(opts.text)),
            el('div', 'mago-field-row', [field, button(opts.submitLabel || L.apply, 'is-primary', submit)]),
            opts.chips ? chips(opts.chips.map(function (c) {
                return {label: c.label, onClick: function () {
                    if (c.value !== undefined) {
                        input.value = c.value;
                    }
                    if (c.onClick) {
                        c.onClick(c);
                    } else {
                        submit();
                    }
                }};
            })) : null
        ]);
        node.magoFocus = function () { input.focus(); };
        return node;
    }

    // S11 Done, with undo: a success callout carrying a temporary undo.
    // {text, onUndo, undoLabel, expiresText}
    function undoCallout(opts) {
        var L = labels(opts);
        var undo = el('button', 'mago-undo-link', opts.undoLabel || L.undo);
        undo.type = 'button';
        if (opts.onUndo) {
            undo.addEventListener('click', opts.onUndo);
        }
        var node = el('div', 'mago-callout is-ok', [
            el('span', 'mago-callout-icon', icon('checkCircle', 17, 2.4)),
            el('div', 'mago-callout-text', [
                el('div', null, content(opts.text)),
                el('div', 'mago-undo-row', [undo, opts.expiresText ? el('span', 'mago-undo-expires', opts.expiresText) : null])
            ])
        ]);
        node.setAttribute('role', 'status');
        return node;
    }

    // S12 Session log: everything Mago changed this session.
    // {title, entries: [{text, time, tone: 'write'|'danger'}], onExport}
    function sessionLog(opts) {
        var L = labels(opts);
        return el('div', 'mago-widget mago-log is-flush', [
            el('div', 'mago-log-head', [
                el('div', 'mago-caption is-grow', opts.title || L.session),
                opts.onExport ? button(L.export, 'is-link', opts.onExport) : null
            ]),
            el('div', 'mago-log-rows', (opts.entries || []).map(function (e) {
                return el('div', 'mago-log-row' + toneClass(e.tone), [
                    el('span', 'mago-log-dot'),
                    el('div', 'mago-log-text', content(e.text)),
                    e.time ? num(e.time, 'mago-log-time') : null
                ]);
            }))
        ]);
    }

    // S14 Skill menu: what Mago can do, with a risk colour per skill.
    // {skills: [{name, title, description, risk: 'read'|'write'|'irreversible', group}],
    //  activeIndex, onSelect, itemClass}
    // A "group" on an entry opens a heading row above it, so one menu can hold several
    // kinds of entry; activeIndex and the onSelect index stay flat over all of them.
    // Returns the menu; call menu.magoSetActive(i) to move the highlight.
    function skillMenu(opts) {
        var L = labels(opts);
        var skillsList = opts.skills || [];
        var activeIndex = opts.activeIndex || 0;
        var items = [];
        var listChildren = [];
        var openGroup = null;
        skillsList.forEach(function (s, i) {
            if (s.group && s.group !== openGroup) {
                openGroup = s.group;
                listChildren.push(el('div', 'mago-menu-group', s.group));
            }
            var risk = s.risk || 'read';
            var row = el('div', 'mago-menu-item' + (opts.itemClass ? ' ' + opts.itemClass : '') + (i === activeIndex ? ' is-active' : ''), [
                el('span', 'mago-risk is-' + risk),
                el('div', 'mago-menu-text', [
                    el('div', 'mago-menu-title', s.title || s.name),
                    s.description ? el('div', 'mago-menu-desc', s.description) : null
                ]),
                mono(s.riskLabel || L.risk[risk] || risk, 'mago-menu-risk')
            ]);
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
            clickable(row, opts.onSelect ? function (e) { opts.onSelect(s, i, e); } : null);
            items.push(row);
            listChildren.push(row);
        });
        var node = el('div', 'mago-menu', [
            opts.hideHead ? null : el('div', 'mago-menu-head', [el('div', 'mago-caption is-grow', opts.title || L.skills), el('div', 'mago-menu-hint', opts.hint || L.menuHint)]),
            el('div', 'mago-menu-items', listChildren)
        ]);
        node.setAttribute('role', 'listbox');
        node.magoSetActive = function (index) {
            items.forEach(function (it, i) {
                it.classList.toggle('is-active', i === index);
                it.setAttribute('aria-selected', i === index ? 'true' : 'false');
            });
            if (items[index] && items[index].scrollIntoView) {
                items[index].scrollIntoView({block: 'nearest'});
            }
        };
        node.magoItems = items;
        return node;
    }

    /* ------------------------------------------------------------------ */
    /* Spec renderer                                                        */
    /* ------------------------------------------------------------------ */

    var BUILDERS = {
        stat: stat,
        stats: stats,
        sparkline: sparkline,
        meter: meter,
        ring: ring,
        composition: composition,
        rankedBars: rankedBars,
        columns: columns,
        lines: lines,
        stackedColumns: stackedColumns,
        heatmap: heatmap,
        funnel: funnel,
        entityList: entityList,
        table: table,
        record: record,
        confirmWrite: confirmWrite,
        toolTrace: toolTrace,
        callout: callout,
        suggestions: suggestions,
        answerFooter: answerFooter,
        empty: empty,
        skeleton: skeleton,
        skillAsk: skillAsk,
        skillRunning: skillRunning,
        skillLine: skillLine,
        skillFailed: skillFailed,
        readLine: readLine,
        skillIrreversible: skillIrreversible,
        skillBulk: skillBulk,
        skillPlan: skillPlan,
        paramPrompt: paramPrompt,
        undoCallout: undoCallout,
        sessionLog: sessionLog,
        skillMenu: skillMenu
    };

    // Build a widget from a plain spec: {type: 'stat', label: ..., ...}.
    // Unknown types return null so a caller can fall back to plain text.
    function render(spec) {
        if (!spec || typeof spec !== 'object' || !BUILDERS[spec.type]) {
            return null;
        }
        return BUILDERS[spec.type](spec);
    }

    // Render a JSON string (a ```mago fenced block) to HTML, or null when it
    // is not a valid spec. Used by the markdown renderer, which needs a string.
    function renderJson(json) {
        var spec;
        try {
            spec = JSON.parse(json);
        } catch (e) {
            // Models sometimes put several widgets in one block as {...},{...}: read that as a list.
            try {
                spec = JSON.parse('[' + json + ']');
            } catch (e2) {
                return null;
            }
        }
        var specs = Array.isArray(spec) ? spec : [spec];
        var wrap = el('div', 'mago-answer');
        var built = 0;
        // The spec came out of the model's answer: no raw HTML from it.
        allowHtml = false;
        try {
            specs.forEach(function (s) {
                var node = render(s);
                if (node) {
                    wrap.appendChild(node);
                    built++;
                }
            });
        } finally {
            allowHtml = true;
        }
        return built ? wrap.outerHTML : null;
    }

    var MagoUI = {
        labels: LABELS,
        icon: icon,
        spinner: spinner,
        button: button,
        badge: badge,
        mono: mono,
        card: card,
        caption: caption,
        callout: callout,
        formatNumber: formatNumber,
        paramsFromInput: paramsFromInput,
        paramTable: paramTable,
        traceStep: traceStep,
        suggestionCard: suggestionCard,
        chips: chips,
        render: render,
        renderJson: renderJson,
        types: Object.keys(BUILDERS)
    };
    Object.keys(BUILDERS).forEach(function (k) { MagoUI[k] = BUILDERS[k]; });

    if (typeof window !== 'undefined') {
        window.MagoUI = MagoUI;
    }
    return MagoUI;
});
