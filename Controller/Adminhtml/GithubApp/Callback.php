<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Controller\Adminhtml\GithubApp;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use MagoAssistant\Mago\Api\Config\RepositoryInterface as ConfigRepository;
use MagoAssistant\Mago\Service\Docs\Source\GitHub\GitHubAppManifest;
use MagoAssistant\Mago\Service\Docs\Source\GitHub\GitHubDocsSource;

/**
 * Landing point for GitHub's App Manifest flow. GitHub redirects here with a one-time ?code
 * after the admin clicks "Create GitHub App"; we exchange it for the App ID + private key,
 * store them (key encrypted), and send the admin on to install the App on their repo.
 */
class Callback extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MagoAssistant_Mago::config';

    // Must match Block\Adminhtml\System\Config\CreateGithubApp::STATE_SESSION_KEY.
    private const STATE_SESSION_KEY = 'mago_github_app_manifest_state';

    public function __construct(
        Context $context,
        private readonly GitHubAppManifest $manifest,
        private readonly WriterInterface $configWriter,
        private readonly EncryptorInterface $encryptor,
        private readonly Session $backendSession,
        private readonly TypeListInterface $cacheTypeList
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('adminhtml/system_config/edit', ['section' => 'mago']);

        $code = (string)$this->getRequest()->getParam('code');
        $state = (string)$this->getRequest()->getParam('state');
        $expectedState = (string)$this->backendSession->getData(self::STATE_SESSION_KEY);
        $this->backendSession->unsetData(self::STATE_SESSION_KEY);

        if ($code === '') {
            $this->messageManager->addErrorMessage(__('GitHub did not return an app code. Please try again.'));
            return $redirect;
        }

        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            $this->messageManager->addErrorMessage(
                __('Security token mismatch while creating the GitHub App. Please start again.')
            );
            return $redirect;
        }

        $app = $this->manifest->convert($code);
        if ($app === null) {
            $this->messageManager->addErrorMessage(
                __('Could not create the GitHub App. Check var/log for details and try again.')
            );
            return $redirect;
        }

        $this->configWriter->save(ConfigRepository::XML_PATH_DOCS_GITHUB_APP_ID, $app['app_id']);
        $this->configWriter->save(
            ConfigRepository::XML_PATH_DOCS_GITHUB_PRIVATE_KEY,
            $this->encryptor->encrypt($app['pem'])
        );
        $this->configWriter->save(ConfigRepository::XML_PATH_DOCS_SOURCE, GitHubDocsSource::CODE_APP);
        $this->cacheTypeList->cleanType('config');

        $name = $app['slug'] !== '' ? $app['slug'] : ('App ' . $app['app_id']);
        $this->messageManager->addSuccessMessage(
            __('GitHub App "%1" created and credentials saved. Now install it on your repository.', $name)
        );

        // Send the admin straight to the installation page; after installing, the docs sync
        // auto-discovers the installation id, so no further copying is needed.
        if ($app['html_url'] !== '') {
            $redirect->setUrl($app['html_url'] . '/installations/new');
        }

        return $redirect;
    }
}
