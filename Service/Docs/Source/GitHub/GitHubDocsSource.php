<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Docs\Source\GitHub;

use Magento\Framework\HTTP\Client\CurlFactory;
use MagoAssistant\Mago\Api\Docs\DocsSourceInterface;
use MagoAssistant\Mago\Logger\ErrorLogger;
use MagoAssistant\Mago\Service\Docs\Source\TarballExtractor;

/**
 * GitHub repository source. Registered twice in di.xml: once anonymous (public repos) and once
 * with a GitHubAppTokenProvider (private repos via a GitHub App installation token).
 */
class GitHubDocsSource implements DocsSourceInterface
{
    public const CODE_PUBLIC = 'github_public';
    public const CODE_APP = 'github_app';

    private const COMMITS_URL = 'https://api.github.com/repos/%s/commits/%s';
    private const TARBALL_URL = 'https://api.github.com/repos/%s/tarball/%s';
    private const BLOB_URL = 'https://github.com/%s/blob/%s/%s';
    private const USER_AGENT = 'MagoAssistant-Mago';

    public function __construct(
        // Own client, not InternalApiClient: GitHub redirects the tarball to codeload, which that client disables.
        private readonly CurlFactory $curlFactory,
        private readonly ErrorLogger $errorLogger,
        private readonly TarballExtractor $extractor,
        private readonly string $code = self::CODE_PUBLIC,
        private readonly string $label = 'GitHub (public repository)',
        private readonly ?GitHubAppTokenProvider $tokenProvider = null
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFileUrl(string $repo, string $ref, string $path): string
    {
        return sprintf(self::BLOB_URL, $repo, $ref, $path);
    }

    /**
     * Authorization header for $repo, or [] when anonymous / when no token is available.
     *
     * @return array<string, string>
     */
    private function authHeaders(string $repo): array
    {
        $token = $this->tokenProvider?->getInstallationToken($repo);
        return $token !== null ? ['Authorization' => 'Bearer ' . $token] : [];
    }

    private function isValidSource(string $repo, string $ref): bool
    {
        return (bool)preg_match('#^[\w.-]+/[\w.-]+$#', $repo)
            && $ref !== ''
            && (bool)preg_match('#^[\w.\-/]+$#', $ref);
    }

    /**
     * The commit SHA the ref currently points at — a cheap (single, small request) change token that
     * lets the sync skip the tarball download when nothing has changed.
     */
    public function fetchRevision(string $repo, string $ref): ?string
    {
        if (!$this->isValidSource($repo, $ref)) {
            $this->errorLogger->addLog('DocsSource', 'Invalid repo/ref: ' . $repo . '@' . $ref);
            return null;
        }

        $url = sprintf(self::COMMITS_URL, $repo, rawurlencode($ref));
        // The .sha media type makes GitHub return the bare 40-char SHA instead of the full commit JSON.
        $body = $this->get($url, ['Accept' => 'application/vnd.github.sha'] + $this->authHeaders($repo));
        if ($body === null) {
            return null;
        }

        $sha = trim($body);
        return $sha !== '' ? $sha : null;
    }

    /**
     * Download the ref as a single tarball and return its indexable docs as [path => contents],
     * where path is repo-relative (e.g. "help/foo.md"). One request instead of one per file; the
     * archive is streamed to disk and parsed a block at a time, so memory stays flat.
     *
     * @return array<string, string>|null null on transport/decode failure (empty array = ref had no docs)
     */
    public function fetchFiles(string $repo, string $ref): ?array
    {
        if (!$this->isValidSource($repo, $ref)) {
            return null;
        }

        $url = sprintf(self::TARBALL_URL, $repo, rawurlencode($ref));
        $tmpPath = $this->download($url, $this->authHeaders($repo), $repo, $ref);
        if ($tmpPath === null) {
            return null;
        }

        try {
            return $this->extractor->extract($tmpPath);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Stream a URL straight to a temp file (never into memory) and return its path, or null on error.
     *
     * @param array<string, string> $headers
     */
    private function download(string $url, array $headers, string $repo, string $ref): ?string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'mago_docs_');
        if ($tmpPath === false) {
            $this->errorLogger->addLog('DocsSource', 'Could not create a temp file for the docs archive');
            return null;
        }

        $fp = fopen($tmpPath, 'wb');
        if ($fp === false) {
            @unlink($tmpPath);
            $this->errorLogger->addLog('DocsSource', 'Could not open the temp file for the docs archive');
            return null;
        }

        $curlHeaders = ['User-Agent: ' . self::USER_AGENT];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $curlHeaders,
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $status !== 200) {
            @unlink($tmpPath);
            $this->errorLogger->addLog(
                'DocsSource',
                'Archive download failed for ' . $repo . '@' . $ref
                . ' (HTTP ' . $status . ($error !== '' ? ', ' . $error : '') . ')'
            );
            return null;
        }

        return $tmpPath;
    }

    /**
     * @param array<string, string> $headers
     */
    private function get(string $url, array $headers = []): ?string
    {
        try {
            $curl = $this->curlFactory->create();
            $curl->setOptions([
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $curl->addHeader('User-Agent', self::USER_AGENT);
            foreach ($headers as $name => $value) {
                $curl->addHeader($name, $value);
            }
            $curl->get($url);

            $status = $curl->getStatus();
            if ($status !== 200) {
                $this->errorLogger->addLog('DocsSource', 'HTTP ' . $status . ' for ' . $url);
                return null;
            }

            return $curl->getBody();
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('DocsSource', $e->getMessage() . ' for ' . $url);
            return null;
        }
    }
}
