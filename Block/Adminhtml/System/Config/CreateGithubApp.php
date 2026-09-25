<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Session;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Math\Random;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Renders a "Create GitHub App" button that submits a pre-filled App manifest to GitHub.
 *
 * GitHub creates the App (with Contents: read-only) and redirects back to our callback
 * controller with a one-time code, which we exchange for the App ID and private key. This
 * spares the admin from creating the App, setting permissions and copying a PEM key by hand.
 */
class CreateGithubApp extends Field
{
    private const STATE_SESSION_KEY = 'mago_github_app_manifest_state';
    private const NAME_MAX_LENGTH = 34;

    public function __construct(
        Context $context,
        private readonly StoreManagerInterface $storeManager,
        private readonly Random $random,
        private readonly Json $json,
        private readonly Session $backendSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $state = $this->random->getRandomString(32);
        $this->backendSession->setData(self::STATE_SESSION_KEY, $state);

        $baseUrl = $this->getStoreBaseUrl();
        $manifest = $this->json->serialize([
            'name' => $this->appName($baseUrl),
            'url' => $baseUrl,
            'redirect_url' => $this->getUrl('mago/githubApp/callback'),
            'public' => false,
            'default_permissions' => ['contents' => 'read', 'metadata' => 'read'],
            'default_events' => [],
        ]);

        $personalUrl = 'https://github.com/settings/apps/new?state=' . rawurlencode($state);
        $orgUrlTemplate = 'https://github.com/organizations/{org}/settings/apps/new?state=' . rawurlencode($state);
        $manifestAttr = $this->escapeHtmlAttr($manifest);
        $personalAttr = $this->escapeHtmlAttr($personalUrl);
        $orgAttr = $this->escapeHtmlAttr($orgUrlTemplate);

        // No <form> here: this block renders inside Magento's config <form>, and nested forms are
        // invalid HTML (the browser drops the inner one, so a submit would just reload the page).
        // Instead the button builds a detached form on document.body and POSTs the manifest to GitHub.
        return <<<HTML
<div id="mago-create-github-app" class="mago-create-github-app"
     data-manifest="{$manifestAttr}" data-personal-url="{$personalAttr}" data-org-url-template="{$orgAttr}">
    <label style="display:block;margin-bottom:6px;font-size:12px;color:#303030">
        GitHub organization <em>(optional — leave blank for your personal account)</em><br>
        <input type="text" class="mago-gh-org input-text" placeholder="my-org"
               style="max-width:280px" autocomplete="off">
    </label>
    <button type="button" class="mago-gh-create action-default scalable">
        <span>Create GitHub App on GitHub</span>
    </button>
    <p style="font-size:12px;color:#606060;margin-top:6px">
        Opens GitHub with the App pre-configured (Contents: read-only). After you click
        <em>Create GitHub App</em> there, you are sent back here with the App ID and private
        key filled in automatically. You then install the App on your private repository.
    </p>
</div>
<script>
    require(["domReady!"], function () {
        var root = document.getElementById("mago-create-github-app");
        if (!root) { return; }
        var btn = root.querySelector(".mago-gh-create");
        var org = root.querySelector(".mago-gh-org");

        btn.addEventListener("click", function () {
            var slug = (org.value || "").trim();
            var action = slug
                ? root.getAttribute("data-org-url-template").replace("{org}", encodeURIComponent(slug))
                : root.getAttribute("data-personal-url");

            var form = document.createElement("form");
            form.method = "POST";
            form.action = action;
            form.style.display = "none";

            var input = document.createElement("input");
            input.type = "hidden";
            input.name = "manifest";
            input.value = root.getAttribute("data-manifest");
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
        });
    });
</script>
HTML;
    }

    private function getStoreBaseUrl(): string
    {
        try {
            return rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * A sensible default App name that fits GitHub's 34-character limit. The admin can still
     * edit it on GitHub before confirming (names are globally unique).
     */
    private function appName(string $baseUrl): string
    {
        $host = (string)parse_url($baseUrl, PHP_URL_HOST);
        $name = $host !== '' ? 'Mago Docs ' . $host : 'Mago Docs';

        return rtrim(mb_substr($name, 0, self::NAME_MAX_LENGTH));
    }
}
