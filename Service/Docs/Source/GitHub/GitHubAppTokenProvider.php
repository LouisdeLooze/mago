<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Docs\Source\GitHub;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Api\Config\RepositoryInterface as ConfigRepository;
use MagoAssistant\Mago\Logger\ErrorLogger;

/**
 * Mints short-lived GitHub App installation tokens so the docs sync can read private repos.
 *
 * Flow: sign a JWT with the App's private key (RS256) -> resolve the installation id
 * (from config, or auto-discover from the repo) -> exchange the JWT for an installation
 * access token. The token is memoized per repo for the lifetime of the process, so a
 * single sync mints one token and reuses it for the tree call and every raw fetch.
 */
class GitHubAppTokenProvider
{
    private const INSTALLATION_LOOKUP_URL = 'https://api.github.com/repos/%s/installation';
    private const ACCESS_TOKEN_URL = 'https://api.github.com/app/installations/%s/access_tokens';
    private const USER_AGENT = 'MagoAssistant-Mago';
    private const JWT_TTL = 540; // 9 minutes; GitHub caps App JWTs at 10.

    /** @var array<string, string|null> */
    private array $tokens = [];

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly ConfigRepository $config,
        private readonly ErrorLogger $errorLogger
    ) {
    }

    /**
     * Installation token for $repo (owner/repo), or null on failure.
     */
    public function getInstallationToken(string $repo): ?string
    {
        if (array_key_exists($repo, $this->tokens)) {
            return $this->tokens[$repo];
        }

        return $this->tokens[$repo] = $this->mintToken($repo);
    }

    private function mintToken(string $repo): ?string
    {
        $appId = $this->config->getDocsGithubAppId();
        $privateKey = $this->config->getDocsGithubPrivateKey();
        if ($appId === '' || $privateKey === '') {
            $this->errorLogger->addLog('DocsAuth', 'GitHub App mode is on but App ID or private key is missing');
            return null;
        }

        $jwt = $this->buildJwt($appId, $privateKey);
        if ($jwt === null) {
            return null;
        }

        $installationId = $this->config->getDocsGithubInstallationId();
        if ($installationId === '') {
            $installationId = $this->discoverInstallationId($repo, $jwt);
            if ($installationId === null) {
                return null;
            }
        }

        $response = $this->postJson(sprintf(self::ACCESS_TOKEN_URL, rawurlencode($installationId)), $jwt);
        $token = is_array($response) ? ($response['token'] ?? null) : null;
        if (!is_string($token) || $token === '') {
            $this->errorLogger->addLog('DocsAuth', 'No installation token returned for ' . $repo);
            return null;
        }

        return $token;
    }

    /**
     * Build and RS256-sign a GitHub App JWT with the built-in openssl functions.
     */
    private function buildJwt(string $appId, string $privateKey): ?string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $payload = [
            'iat' => $now - 60, // allow for clock drift on GitHub's side
            'exp' => $now + self::JWT_TTL,
            'iss' => $appId,
        ];

        $signingInput = $this->base64UrlEncode($this->json->serialize($header))
            . '.' . $this->base64UrlEncode($this->json->serialize($payload));

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            $this->errorLogger->addLog('DocsAuth', 'GitHub App private key is not a valid PEM key');
            return null;
        }

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            $this->errorLogger->addLog('DocsAuth', 'Failed to sign GitHub App JWT');
            return null;
        }

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    private function discoverInstallationId(string $repo, string $jwt): ?string
    {
        $response = $this->getJson(sprintf(self::INSTALLATION_LOOKUP_URL, $repo), $jwt);
        $id = is_array($response) ? ($response['id'] ?? null) : null;
        if ($id === null) {
            $this->errorLogger->addLog(
                'DocsAuth',
                'Could not find a GitHub App installation on ' . $repo . ' — is the App installed on it?'
            );
            return null;
        }

        return (string)$id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getJson(string $url, string $jwt): ?array
    {
        try {
            $curl = $this->newCurl($jwt);
            $curl->get($url);
            return $this->decode($curl->getStatus(), $curl->getBody(), $url);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('DocsAuth', $e->getMessage() . ' for ' . $url);
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function postJson(string $url, string $jwt): ?array
    {
        try {
            $curl = $this->newCurl($jwt);
            $curl->post($url, '');
            return $this->decode($curl->getStatus(), $curl->getBody(), $url);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('DocsAuth', $e->getMessage() . ' for ' . $url);
            return null;
        }
    }

    private function newCurl(string $jwt): \Magento\Framework\HTTP\Client\Curl
    {
        $curl = $this->curlFactory->create();
        $curl->setOptions([
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $curl->addHeader('User-Agent', self::USER_AGENT);
        $curl->addHeader('Accept', 'application/vnd.github+json');
        $curl->addHeader('Authorization', 'Bearer ' . $jwt);
        return $curl;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(int $status, ?string $body, string $url): ?array
    {
        // 201 for the token endpoint, 200 for the installation lookup.
        if ($status !== 200 && $status !== 201) {
            $this->errorLogger->addLog('DocsAuth', 'HTTP ' . $status . ' for ' . $url);
            return null;
        }

        try {
            $data = $this->json->unserialize((string)$body);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('DocsAuth', 'Invalid JSON from ' . $url . ': ' . $e->getMessage());
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
