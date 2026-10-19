<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\ViewModel;

use Magento\Cookie\Helper\Cookie;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use WebRamos\GoogleTagManager\Model\Config;
use WebRamos\GoogleTagManager\Model\Config\Source\ConsentMode;
use WebRamos\GoogleTagManager\Model\Consent\RegionList;
use WebRamos\GoogleTagManager\Model\HostGuard;

/**
 * Everything the head and noscript templates render, or null when nothing may render.
 *
 * Nothing renders when the container is off for the store, its id is not a GTM id, or the
 * store's host is not an allowed production host. Resolved once per request: both
 * templates ask.
 */
class Loader implements ArgumentInterface
{
    private const MODULE_NAME = 'WebRamos_GoogleTagManager';

    private const LOADER_FILE = '/frontend/web/js/loader.js';

    /**
     * @var array|false|null Resolved settings, false when nothing renders, null before the first call
     */
    private array|false|null $settings = null;

    private ?string $loaderScript = null;

    public function __construct(
        private readonly Config $config,
        private readonly HostGuard $hostGuard,
        private readonly StoreManagerInterface $storeManager,
        private readonly RegionList $regionList,
        private readonly Json $json,
        private readonly Reader $moduleReader,
        private readonly File $file
    ) {
    }

    /**
     * Settings handed to the loader script, or null when the container must not render.
     *
     * @return array|null
     */
    public function getSettings(): ?array
    {
        if ($this->settings === null) {
            $this->settings = $this->resolve() ?? false;
        }

        return $this->settings === false ? null : $this->settings;
    }

    /**
     * Settings as JSON for the loader's data attribute.
     *
     * @return string
     */
    public function getSettingsJson(): string
    {
        $settings = $this->getSettings() ?? [];
        unset($settings['noscript']);

        return (string)$this->json->serialize($settings);
    }

    /**
     * URL of the noscript iframe, or null when the container must not render.
     *
     * @return string|null
     */
    public function getNoscriptUrl(): ?string
    {
        $settings = $this->getSettings();

        return $settings === null ? null : $settings['noscript'];
    }

    /**
     * Body of the inline loader: the module's static loader.js without its file header.
     *
     * Kept in a real .js file so the logic is never assembled in PHP; inlined (not
     * loaded by src) because Consent Mode defaults must be set before gtm.js, in the
     * same synchronous pass.
     *
     * @return string
     */
    public function getLoaderScript(): string
    {
        if ($this->loaderScript === null) {
            $path = $this->moduleReader->getModuleDir(Dir::MODULE_VIEW_DIR, self::MODULE_NAME) . self::LOADER_FILE;
            $script = $this->file->fileGetContents($path);
            $this->loaderScript = trim((string)preg_replace('#^\s*/\*.*?\*/\s*#s', '', $script, 1));
        }

        return $this->loaderScript;
    }

    /**
     * Build the settings for the current store, or null when nothing may render.
     *
     * @return array|null
     */
    private function resolve(): ?array
    {
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();

        if (!$this->config->isEnabled($storeId)) {
            return null;
        }
        $containerId = $this->config->getContainerId($storeId);
        if ($containerId === null || !$this->hostGuard->isAllowed($storeId)) {
            return null;
        }

        $baseUrl = $this->config->getGtmBaseUrl($storeId);

        return [
            'src' => $baseUrl . '/gtm.js?id=' . rawurlencode($containerId),
            'noscript' => $baseUrl . '/ns.html?id=' . rawurlencode($containerId),
            'exposeNonce' => $this->config->isNonceExposed($storeId),
            'consent' => $this->resolveConsent($storeId, (int)$store->getWebsiteId()),
        ];
    }

    /**
     * Consent Mode settings, or null when consent commands are off.
     *
     * @param int $storeId
     * @param int $websiteId
     * @return array|null
     */
    private function resolveConsent(int $storeId, int $websiteId): ?array
    {
        $mode = $this->config->getConsentMode($storeId);
        if ($mode === ConsentMode::OFF) {
            return null;
        }

        $consent = [
            'mode' => $mode,
            'regions' => $this->regionList->getCodes(),
            'elsewhere' => $this->config->isGrantedElsewhere($storeId) ? 'granted' : 'denied',
            'waitForUpdate' => $this->config->getWaitForUpdate($storeId),
            'adsDataRedaction' => $this->config->isAdsDataRedaction($storeId),
            'urlPassthrough' => $this->config->isUrlPassthrough($storeId),
        ];
        if ($mode === ConsentMode::MAGENTO) {
            $consent['cookie'] = Cookie::IS_USER_ALLOWED_SAVE_COOKIE;
            $consent['websiteId'] = $websiteId;
        }

        return $consent;
    }
}
