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
use MagoAssistant\Mago\Service\Docs\Source\GitHub\GitHubAppTokenProvider;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeConfigRepository;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class GitHubAppTokenProviderTest extends TestCase
{
    private const REPO = 'acme/private-docs';

    private string $privateKeyPem = '';
    private string $publicKeyPem = '';
    private FakeConfigRepository $config;
    private CurlFactory&MockObject $curlFactory;
    /** @var list<string> URLs requested, in order */
    private array $requestedUrls = [];
    /** @var array<string, string> headers on the most recent curl */
    private array $lastHeaders = [];

    protected function setUp(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key, 'openssl is required for this test');
        openssl_pkey_export($key, $this->privateKeyPem);
        $this->publicKeyPem = openssl_pkey_get_details($key)['key'];

        $this->config = (new FakeConfigRepository())
            ->withDocsGithubAppId('123456')
            ->withDocsGithubPrivateKey($this->privateKeyPem);

        $this->curlFactory = $this->createMock(CurlFactory::class);
        $this->requestedUrls = [];
        $this->lastHeaders = [];
    }

    private function newProvider(): GitHubAppTokenProvider
    {
        return new GitHubAppTokenProvider(
            $this->curlFactory,
            new Json(),
            $this->config,
            new ErrorLogger(new FakeLogger(), new Json())
        );
    }

    /**
     * A curl stub that records its request URL + headers and replays a canned response.
     */
    private function makeCurl(int $status, string $body): Curl&Stub
    {
        $curl = $this->createStub(Curl::class);
        $headers = [];
        $curl->method('addHeader')->willReturnCallback(function ($name, $value) use (&$headers): void {
            $headers[$name] = $value;
            $this->lastHeaders = $headers;
        });
        $curl->method('get')->willReturnCallback(function ($url): void {
            $this->requestedUrls[] = $url;
        });
        $curl->method('post')->willReturnCallback(function ($url): void {
            $this->requestedUrls[] = $url;
        });
        $curl->method('getStatus')->willReturn($status);
        $curl->method('getBody')->willReturn($body);

        return $curl;
    }

    /**
     * @param list<Curl&Stub> $curls
     */
    private function expectCurls(array $curls): void
    {
        $this->curlFactory->expects($this->exactly(count($curls)))
            ->method('create')
            ->willReturnOnConsecutiveCalls(...$curls);
    }

    #[Test]
    public function mintsTokenWithExplicitInstallationId(): void
    {
        $this->config->withDocsGithubInstallationId('999');
        $this->expectCurls([$this->makeCurl(201, '{"token":"ghs_secret"}')]);

        $token = $this->newProvider()->getInstallationToken(self::REPO);

        self::assertSame('ghs_secret', $token);
        self::assertSame(
            ['https://api.github.com/app/installations/999/access_tokens'],
            $this->requestedUrls
        );
        $this->assertValidAppJwt($this->lastHeaders['Authorization'] ?? '');
    }

    #[Test]
    public function autoDiscoversInstallationIdWhenBlank(): void
    {
        $this->expectCurls([
            $this->makeCurl(200, '{"id":4242}'),
            $this->makeCurl(201, '{"token":"ghs_discovered"}'),
        ]);

        $token = $this->newProvider()->getInstallationToken(self::REPO);

        self::assertSame('ghs_discovered', $token);
        self::assertSame([
            'https://api.github.com/repos/acme/private-docs/installation',
            'https://api.github.com/app/installations/4242/access_tokens',
        ], $this->requestedUrls);
    }

    #[Test]
    public function returnsNullWhenInstallationNotFound(): void
    {
        $this->expectCurls([$this->makeCurl(404, '{"message":"Not Found"}')]);

        self::assertNull($this->newProvider()->getInstallationToken(self::REPO));
    }

    #[Test]
    public function returnsNullWhenCredentialsMissing(): void
    {
        $this->config->withDocsGithubPrivateKey('');
        $this->curlFactory->expects($this->never())->method('create');

        self::assertNull($this->newProvider()->getInstallationToken(self::REPO));
    }

    #[Test]
    public function returnsNullOnInvalidPrivateKey(): void
    {
        $this->config->withDocsGithubPrivateKey('not-a-pem-key');
        $this->curlFactory->expects($this->never())->method('create');

        self::assertNull($this->newProvider()->getInstallationToken(self::REPO));
    }

    #[Test]
    public function memoizesTokenPerRepo(): void
    {
        $this->config->withDocsGithubInstallationId('999');
        // Only one curl: a second create() call would fail the exactly(1) expectation.
        $this->expectCurls([$this->makeCurl(201, '{"token":"ghs_once"}')]);

        $provider = $this->newProvider();
        self::assertSame('ghs_once', $provider->getInstallationToken(self::REPO));
        self::assertSame('ghs_once', $provider->getInstallationToken(self::REPO));
    }

    private function assertValidAppJwt(string $authorization): void
    {
        self::assertStringStartsWith('Bearer ', $authorization);
        $jwt = substr($authorization, 7);
        [$h, $p, $sig] = explode('.', $jwt);

        $signature = base64_decode(strtr($sig, '-_', '+/'));
        $verified = openssl_verify($h . '.' . $p, $signature, $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $verified, 'JWT signature must verify against the App public key');

        $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        self::assertSame('123456', $payload['iss']);
        self::assertGreaterThan(time(), $payload['exp']);
        self::assertLessThanOrEqual(time(), $payload['iat']);
    }
}
