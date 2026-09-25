<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Docs\Source\GitHub;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Logger\ErrorLogger;
use MagoAssistant\Mago\Service\Docs\Source\GitHub\GitHubAppManifest;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class GitHubAppManifestTest extends TestCase
{
    private CurlFactory&Stub $curlFactory;
    private ?string $requestedUrl = null;

    protected function setUp(): void
    {
        $this->curlFactory = $this->createStub(CurlFactory::class);
        $this->requestedUrl = null;
    }

    private function newService(): GitHubAppManifest
    {
        return new GitHubAppManifest(
            $this->curlFactory,
            new Json(),
            new ErrorLogger(new FakeLogger(), new Json())
        );
    }

    private function stubCurl(int $status, string $body): void
    {
        $curl = $this->createStub(Curl::class);
        $curl->method('post')->willReturnCallback(function ($url): void {
            $this->requestedUrl = $url;
        });
        $curl->method('getStatus')->willReturn($status);
        $curl->method('getBody')->willReturn($body);
        $this->curlFactory->method('create')->willReturn($curl);
    }

    #[Test]
    public function convertsCodeIntoCredentials(): void
    {
        $this->stubCurl(201, '{"id":424242,"slug":"mago-docs","html_url":"https://github.com/apps/mago-docs","pem":"-----BEGIN RSA PRIVATE KEY-----\nabc\n-----END RSA PRIVATE KEY-----"}');

        $result = $this->newService()->convert('the-code');

        self::assertNotNull($result);
        self::assertSame('424242', $result['app_id']);
        self::assertStringContainsString('BEGIN RSA PRIVATE KEY', $result['pem']);
        self::assertSame('mago-docs', $result['slug']);
        self::assertSame('https://github.com/apps/mago-docs', $result['html_url']);
        self::assertSame('https://api.github.com/app-manifests/the-code/conversions', $this->requestedUrl);
    }

    #[Test]
    public function returnsNullOnEmptyCode(): void
    {
        $this->curlFactory = $this->createStub(CurlFactory::class);
        self::assertNull($this->newService()->convert(''));
    }

    #[Test]
    public function returnsNullOnNonCreatedStatus(): void
    {
        $this->stubCurl(422, '{"message":"Validation Failed"}');
        self::assertNull($this->newService()->convert('the-code'));
    }

    #[Test]
    public function returnsNullWhenResponseMissingKey(): void
    {
        $this->stubCurl(201, '{"id":424242}');
        self::assertNull($this->newService()->convert('the-code'));
    }
}
