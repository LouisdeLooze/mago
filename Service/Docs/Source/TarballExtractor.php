<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Docs\Source;

use MagoAssistant\Mago\Logger\ErrorLogger;

/**
 * Streams a GitHub .tar.gz and pulls out its help/**.md entries without ever holding the whole
 * (potentially very large — the default docs mirror is ~170 MB) archive in memory. It reads the
 * gzip stream a block at a time, keeps only the small markdown files we index, and discards
 * everything else (images and other assets) as it goes.
 */
class TarballExtractor
{
    private const BLOCK = 512;
    private const SKIP_CHUNK = 65536;

    public function __construct(
        private readonly ErrorLogger $errorLogger
    ) {
    }

    /**
     * @return array<string, string>|null [repo-relative path => contents], or null if the file is
     *                                     not a readable gzip stream (empty array = no docs inside)
     */
    public function extract(string $gzipPath): ?array
    {
        $handle = fopen($gzipPath, 'rb');
        if ($handle === false) {
            $this->errorLogger->addLog('DocsSource', 'Could not open downloaded archive');
            return null;
        }
        $magic = fread($handle, 2);
        fclose($handle);
        if ($magic !== "\x1f\x8b") {
            $this->errorLogger->addLog('DocsSource', 'Downloaded archive is not a gzip stream');
            return null;
        }

        $gz = gzopen($gzipPath, 'rb');
        if ($gz === false) {
            $this->errorLogger->addLog('DocsSource', 'Could not open gzip stream');
            return null;
        }

        try {
            return $this->readEntries($gz);
        } finally {
            gzclose($gz);
        }
    }

    /**
     * @param resource $gz
     * @return array<string, string>
     */
    private function readEntries($gz): array
    {
        $files = [];
        // A GNU long name or pax "path=" header names the entry that follows it.
        $nameOverride = null;

        while (true) {
            $header = $this->readFully($gz, self::BLOCK);
            if (strlen($header) < self::BLOCK || trim($header, "\0") === '') {
                break; // short read or the all-zero block that terminates the archive
            }

            $size = (int)octdec(trim(substr($header, 124, 12), "\0 "));
            $type = substr($header, 156, 1);
            $padded = (int)(ceil($size / self::BLOCK) * self::BLOCK);

            if ($type === 'x') { // pax extended header for the next entry
                $nameOverride = $this->paxPath(substr($this->readFully($gz, $padded), 0, $size));
                continue;
            }
            if ($type === 'g') { // pax global header (commit metadata) — ignore
                $this->skip($gz, $padded);
                continue;
            }
            if ($type === 'L') { // GNU long name for the next entry
                $nameOverride = rtrim(substr($this->readFully($gz, $padded), 0, $size), "\0");
                continue;
            }

            // Regular file entries only ('0' or the historical NUL/empty typeflag).
            if ($type !== '0' && $type !== "\0" && $type !== '') {
                $this->skip($gz, $padded);
                $nameOverride = null;
                continue;
            }

            $name = $nameOverride ?? $this->entryName($header);
            $nameOverride = null;

            $path = $this->stripTopDir($name);
            if ($path !== null && str_starts_with($path, 'help/') && str_ends_with($path, '.md')) {
                $files[$path] = substr($this->readFully($gz, $padded), 0, $size);
            } else {
                $this->skip($gz, $padded);
            }
        }

        return $files;
    }

    private function entryName(string $header): string
    {
        $name = rtrim(substr($header, 0, 100), "\0");
        $prefix = rtrim(substr($header, 345, 155), "\0");
        return $prefix !== '' ? $prefix . '/' . $name : $name;
    }

    /**
     * GitHub wraps the archive in a single top-level "{owner}-{repo}-{sha}/" directory; strip it so
     * paths are repo-relative. Returns null for the top-level dir entry itself.
     */
    private function stripTopDir(string $name): ?string
    {
        $pos = strpos($name, '/');
        if ($pos === false) {
            return null;
        }
        $rel = substr($name, $pos + 1);
        return $rel !== '' ? $rel : null;
    }

    private function paxPath(string $record): ?string
    {
        // pax records look like: "<length> path=<value>\n"
        return preg_match('/^\d+ path=([^\n]*)\n/m', $record, $m) === 1 ? $m[1] : null;
    }

    /**
     * @param resource $gz
     */
    private function readFully($gz, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length && !gzeof($gz)) {
            $chunk = gzread($gz, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    /**
     * @param resource $gz
     */
    private function skip($gz, int $length): void
    {
        while ($length > 0 && !gzeof($gz)) {
            $chunk = gzread($gz, (int)min($length, self::SKIP_CHUNK));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $length -= strlen($chunk);
        }
    }
}
