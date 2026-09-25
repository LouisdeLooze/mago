<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use Magento\Store\Api\Data\StoreInterface;
use MagoAssistant\Mago\Api\Config\RepositoryInterface;

class FakeConfigRepository implements RepositoryInterface
{
    private bool $isInternalSslVerifyEnabled = true;
    private string $internalUrl = '';
    private int $maxToolIterations = 0;
    private bool $answerWidgets = false;    private int $maxResponseTokens = 0;
    private bool $docsEnabled = false;
    private string $docsSourceCode = 'github_public';
    private string $docsSourceRepo = '';
    private string $docsRef = '';
    private string $docsGithubAppId = '';
    private string $docsGithubInstallationId = '';
    private string $docsGithubPrivateKey = '';
    private bool $docsDescribeEnabled = false;
    private string $docsDescription = '';

    public function withDocsDescribeEnabled(bool $enabled): self
    {
        $this->docsDescribeEnabled = $enabled;

        return $this;
    }

    public function withDocsDescription(string $description): self
    {
        $this->docsDescription = $description;

        return $this;
    }

    public function withDocsEnabled(bool $enabled): self
    {
        $this->docsEnabled = $enabled;

        return $this;
    }

    public function withDocsRepo(string $repo, string $ref): self
    {
        $this->docsSourceRepo = $repo;
        $this->docsRef = $ref;

        return $this;
    }

    public function withDocsSourceCode(string $code): self
    {
        $this->docsSourceCode = $code;

        return $this;
    }

    public function withDocsGithubAppId(string $appId): self
    {
        $this->docsGithubAppId = $appId;

        return $this;
    }

    public function withDocsGithubInstallationId(string $installationId): self
    {
        $this->docsGithubInstallationId = $installationId;

        return $this;
    }

    public function withDocsGithubPrivateKey(string $privateKey): self
    {
        $this->docsGithubPrivateKey = $privateKey;

        return $this;
    }

    public function withMaxToolIterations(int $maxToolIterations): self
    {
        $this->maxToolIterations = $maxToolIterations;

        return $this;
    }

    public function withMaxResponseTokens(int $maxResponseTokens): self
    {
        $this->maxResponseTokens = $maxResponseTokens;

        return $this;
    }

    public function withAnswerWidgets(bool $enabled): self
    {
        $this->answerWidgets = $enabled;

        return $this;
    }

    public function isAnswerWidgetsEnabled(): bool
    {
        return $this->answerWidgets;
    }
    public function withInternalSslVerifyEnabled(bool $isEnabled): self
    {
        $this->isInternalSslVerifyEnabled = $isEnabled;

        return $this;
    }

    public function withInternalUrl(string $internalUrl): self
    {
        $this->internalUrl = $internalUrl;

        return $this;
    }

    public function isInternalSslVerifyEnabled(): bool
    {
        return $this->isInternalSslVerifyEnabled;
    }

    public function getInternalUrl(): string
    {
        return $this->internalUrl;
    }

    public function getExtensionVersion(): string
    {
        return '1.0.0';
    }

    public function getExtensionCode(): string
    {
        return self::EXTENSION_CODE;
    }

    public function getMagentoVersion(): string
    {
        return '2.4.7';
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return true;
    }

    public function getStore(?int $storeId = null): StoreInterface
    {
        throw new \LogicException('FakeConfigRepository does not provide stores');
    }

    public function getSupportLink(): string
    {
        return '';
    }

    public function isDebugEnabled(): bool
    {
        return false;
    }

    public function getProvider(): string
    {
        return '';
    }

    public function getApiKey(): string
    {
        return '';
    }

    public function getModel(): string
    {
        return '';
    }

    public function getMaxTokens(): int
    {
        return 0;
    }

    public function getTemperature(): float
    {
        return 0.0;
    }

    public function isStreamingEnabled(): bool
    {
        return false;
    }

    public function getSystemPrompt(): string
    {
        return '';
    }

    public function getMaxToolIterations(): int
    {
        return $this->maxToolIterations;
    }

    public function getAccentColor(): string
    {
        return '';
    }

    public function getTextColor(): string
    {
        return '';
    }

    public function getAssistantName(): string
    {
        return '';
    }

    public function getLanguage(): string
    {
        return '';
    }

    public function isDocsEnabled(): bool
    {
        return $this->docsEnabled;
    }

    public function getDocsSourceRepo(): string
    {
        return $this->docsSourceRepo;
    }

    public function getDocsRef(): string
    {
        return $this->docsRef;
    }

    public function getDocsTopK(): int
    {
        return 0;
    }

    public function getDocsSourceCode(): string
    {
        return $this->docsSourceCode;
    }

    public function getDocsGithubAppId(): string
    {
        return $this->docsGithubAppId;
    }

    public function getDocsGithubInstallationId(): string
    {
        return $this->docsGithubInstallationId;
    }

    public function getDocsGithubPrivateKey(): string
    {
        return $this->docsGithubPrivateKey;
    }

    public function isDocsDescribeEnabled(): bool
    {
        return $this->docsDescribeEnabled;
    }

    public function getDocsDescription(): string
    {
        return $this->docsDescription;
    }

    public function getPayloadRetentionDays(): int
    {
        return 0;
    }

    public function getAiServiceId(): string
    {
        return '';
    }

    public function getMaxResponseTokens(): int
    {
        return $this->maxResponseTokens;
    }
}
