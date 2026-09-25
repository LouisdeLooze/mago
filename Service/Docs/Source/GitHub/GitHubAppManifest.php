<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Docs\Source\GitHub;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Logger\ErrorLogger;

/**
 * Converts a GitHub App Manifest "code" (handed back after the user clicks "Create GitHub App")
 * into the created App's credentials. This is the second half of GitHub's manifest flow:
 * https://docs.github.com/en/apps/sharing-github-apps/registering-a-github-app-from-a-manifest
 */
class GitHubAppManifest
{
    private const CONVERSIONS_URL = 'https://api.github.com/app-manifests/%s/conversions';
    private const USER_AGENT = 'MagoAssistant-Mago';

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly ErrorLogger $errorLogger
    ) {
    }

    /**
     * Exchange the temporary manifest code for the new App's id, private key and URLs.
     *
     * @return array{app_id: string, pem: string, slug: string, html_url: string}|null
     */
    public function convert(string $code): ?array
    {
        if ($code === '') {
            return null;
        }

        try {
            $curl = $this->curlFactory->create();
            $curl->setOptions([
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $curl->addHeader('User-Agent', self::USER_AGENT);
            $curl->addHeader('Accept', 'application/vnd.github+json');
            $curl->post(sprintf(self::CONVERSIONS_URL, rawurlencode($code)), '');

            $status = $curl->getStatus();
            if ($status !== 201) {
                $this->errorLogger->addLog('DocsAuth', 'App manifest conversion returned HTTP ' . $status);
                return null;
            }

            $data = $this->json->unserialize((string)$curl->getBody());
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('DocsAuth', 'App manifest conversion failed: ' . $e->getMessage());
            return null;
        }

        $appId = isset($data['id']) ? (string)$data['id'] : '';
        $pem = isset($data['pem']) ? (string)$data['pem'] : '';
        if ($appId === '' || $pem === '') {
            $this->errorLogger->addLog('DocsAuth', 'App manifest conversion response missing id/pem');
            return null;
        }

        return [
            'app_id' => $appId,
            'pem' => $pem,
            'slug' => isset($data['slug']) ? (string)$data['slug'] : '',
            'html_url' => isset($data['html_url']) ? (string)$data['html_url'] : '',
        ];
    }
}
