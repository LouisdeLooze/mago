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
use MagoAssistant\Mago\Service\Docs\Source\GitHub\GitHubDocsSource;
use MagoAssistant\Mago\Service\Docs\Source\TarballExtractor;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class GitHubDocsSourceTest extends TestCase
{
    private const REPO = 'acme/private-docs';
    private const REF = 'main';

    private CurlFactory&Stub $curlFactory;
    private GitHubAppTokenProvider&Stub $tokenProvider;
    private ?string $requestedUrl = null;
    /** @var array<string, string> */
    private array $headers = [];

    protected function setUp(): void
    {
        $this->curlFactory = $this->createStub(CurlFactory::class);
        $this->tokenProvider = $this->createStub(GitHubAppTokenProvider::class);
        $this->requestedUrl = null;
        $this->headers = [];
    }

    private function newSource(bool $authenticated = true): GitHubDocsSource
    {
        return new GitHubDocsSource(
            $this->curlFactory,
            new ErrorLogger(new FakeLogger(), new Json()),
            new TarballExtractor(new ErrorLogger(new FakeLogger(), new Json())),
            $authenticated ? GitHubDocsSource::CODE_APP : GitHubDocsSource::CODE_PUBLIC,
            'GitHub',
            $authenticated ? $this->tokenProvider : null
        );
    }

    private function stubCurl(int $status, string $body): void
    {
        $curl = $this->createStub(Curl::class);
        $curl->method('addHeader')->willReturnCallback(function ($name, $value): void {
            $this->headers[$name] = $value;
        });
        $curl->method('get')->willReturnCallback(function ($url): void {
            $this->requestedUrl = $url;
        });
        $curl->method('getStatus')->willReturn($status);
        $curl->method('getBody')->willReturn($body);
        $this->curlFactory->method('create')->willReturn($curl);
    }

    #[Test]
    public function fetchRevisionReturnsBareShaFromCommitsEndpoint(): void
    {
        $this->tokenProvider->method('getInstallationToken')->willReturn(null);
        $this->stubCurl(200, "d34db33fca11ab1e\n");

        $sha = $this->newSource()->fetchRevision(self::REPO, self::REF);

        self::assertSame('d34db33fca11ab1e', $sha);
        self::assertSame('https://api.github.com/repos/acme/private-docs/commits/main', $this->requestedUrl);
        self::assertArrayNotHasKey('Authorization', $this->headers);
    }

    #[Test]
    public function fetchRevisionSendsBearerWhenAuthenticated(): void
    {
        $this->tokenProvider->method('getInstallationToken')->willReturn('ghs_secret');
        $this->stubCurl(200, "abc123\n");

        $this->newSource()->fetchRevision(self::REPO, self::REF);

        self::assertSame('Bearer ghs_secret', $this->headers['Authorization']);
        self::assertSame('application/vnd.github.sha', $this->headers['Accept']);
    }

    #[Test]
    public function fetchRevisionReturnsNullOnHttpError(): void
    {
        $this->tokenProvider->method('getInstallationToken')->willReturn(null);
        $this->stubCurl(404, 'Not Found');

        self::assertNull($this->newSource()->fetchRevision(self::REPO, self::REF));
    }

    #[Test]
    public function rejectsAnInvalidRepo(): void
    {
        $this->tokenProvider->method('getInstallationToken')->willReturn(null);

        self::assertNull($this->newSource()->fetchRevision('not a repo', self::REF));
        self::assertNull($this->newSource()->fetchFiles('not a repo', self::REF));
    }

    #[Test]
    public function anonymousSourceNeverSendsAuthorization(): void
    {
        $this->stubCurl(200, "abc123\n");

        $source = $this->newSource(authenticated: false);

        self::assertSame('abc123', $source->fetchRevision(self::REPO, self::REF));
        self::assertArrayNotHasKey('Authorization', $this->headers);
        self::assertSame(GitHubDocsSource::CODE_PUBLIC, $source->getCode());
    }

    #[Test]
    public function buildsBlobUrlForAFile(): void
    {
        self::assertSame(
            'https://github.com/acme/private-docs/blob/main/help/foo.md',
            $this->newSource()->getFileUrl(self::REPO, self::REF, 'help/foo.md')
        );
    }
}
