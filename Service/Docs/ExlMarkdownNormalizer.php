<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Docs;

class ExlMarkdownNormalizer
{
    /**
     * @param callable(string):?string $includeResolver resolves an include path to raw markdown
     * @return array{title: string, description: string, tags: string, content: string}
     */
    public function normalize(string $raw, callable $includeResolver): array
    {
        $title = '';
        $description = '';
        $tags = '';
        $body = $raw;

        if (preg_match('/^---\s*\R(.*?)\R---\s*\R?(.*)$/s', $raw, $m)) {
            $frontMatter = $m[1];
            $body = $m[2];

            if (preg_match('/^title:\s*(.+?)\s*$/m', $frontMatter, $t)) {
                $title = $this->cleanScalar($t[1]);
            }
            if (preg_match('/^description:\s*(.+?)\s*$/m', $frontMatter, $d)) {
                $description = $this->cleanScalar($d[1]);
            }
            if (preg_match('/^feature:\s*(.+?)\s*$/m', $frontMatter, $f)) {
                $tags = trim((string)preg_replace('/["\[\]]/', '', $f[1]));
            }
        }

        for ($depth = 0; $depth < 3; $depth++) {
            $replaced = 0;
            $body = (string)preg_replace_callback(
                '/\{\{\$include\s+([^\}]+)\}\}/',
                static function (array $mm) use ($includeResolver): string {
                    $resolved = $includeResolver(trim($mm[1]));
                    return $resolved !== null ? "\n" . $resolved . "\n" : '';
                },
                $body,
                -1,
                $replaced
            );
            if ($replaced === 0) {
                break;
            }
        }

        // Unwrap Experience League inline markup.
        $body = (string)preg_replace('/\[!(?:UICONTROL|DNL)\s+([^\]]+)\]/', '$1', $body);

        // Strip attribute blocks such as {width="700" zoomable="yes"}.
        $body = (string)preg_replace('/\{[^}\n]*\b(?:width|height|zoomable|align|border|class)=[^}\n]*\}/', '', $body);

        return [
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
            'content' => trim((string)$body),
        ];
    }

    /**
     * Whether a repo path should be indexed as a standalone doc.
     */
    public function isIndexable(string $path): bool
    {
        if (!str_ends_with($path, '.md')) {
            return false;
        }
        if (str_starts_with($path, 'help/_includes/')) {
            return false;
        }
        if (basename($path) === 'TOC.md') {
            return false;
        }
        return true;
    }

    /**
     * Adobe Commerce-only areas that Magento Open Source / Mage-OS may not have.
     */
    public function edition(string $path): ?string
    {
        if (str_starts_with($path, 'help/b2b/') || str_starts_with($path, 'help/page-builder/')) {
            return 'commerce';
        }
        return null;
    }

    private function cleanScalar(string $value): string
    {
        $value = trim($value);
        if (strlen($value) >= 2
            && (($value[0] === '"' && str_ends_with($value, '"'))
                || ($value[0] === "'" && str_ends_with($value, "'")))
        ) {
            $value = substr($value, 1, -1);
        }
        return trim($value);
    }
}
