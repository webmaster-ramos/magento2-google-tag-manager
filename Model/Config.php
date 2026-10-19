<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use WebRamos\GoogleTagManager\Model\Config\Source\ConsentMode;

/**
 * Typed reader for the module's configuration section.
 */
class Config
{
    public const XML_ENABLED = 'webramos_google_tag_manager/general/enabled';
    public const XML_CONTAINER_ID = 'webramos_google_tag_manager/general/container_id';
    public const XML_ALLOWED_HOSTS = 'webramos_google_tag_manager/general/allowed_hosts';
    public const XML_GTM_BASE_URL = 'webramos_google_tag_manager/general/gtm_base_url';
    public const XML_CSP_HOSTS = 'webramos_google_tag_manager/csp/additional_hosts';
    public const XML_EXPOSE_NONCE = 'webramos_google_tag_manager/csp/expose_nonce';
    public const XML_CONSENT_MODE = 'webramos_google_tag_manager/consent/mode';
    public const XML_CONSENT_DEFAULT_ELSEWHERE = 'webramos_google_tag_manager/consent/default_elsewhere';
    public const XML_CONSENT_WAIT = 'webramos_google_tag_manager/consent/wait_for_update';
    public const XML_ADS_DATA_REDACTION = 'webramos_google_tag_manager/consent/ads_data_redaction';
    public const XML_URL_PASSTHROUGH = 'webramos_google_tag_manager/consent/url_passthrough';

    public const DEFAULT_GTM_BASE_URL = 'https://www.googletagmanager.com';

    public const CONTAINER_ID_PATTERN = '/^GTM-[A-Z0-9]{4,12}$/';

    public const WAIT_FOR_UPDATE_MAX = 10000;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly HostList $hostList
    ) {
    }

    /**
     * Whether the container is switched on for the store.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Container id of the store, or null when none is set or it is not a GTM id.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getContainerId(?int $storeId = null): ?string
    {
        $id = $this->normalizeContainerId((string)$this->getValue(self::XML_CONTAINER_ID, $storeId));

        return preg_match(self::CONTAINER_ID_PATTERN, $id) ? $id : null;
    }

    /**
     * Production hosts the container may load on. Installation-wide (default scope only).
     *
     * @return string[]
     */
    public function getAllowedHosts(): array
    {
        return $this->hostList->parse((string)$this->scopeConfig->getValue(self::XML_ALLOWED_HOSTS));
    }

    /**
     * Base URL gtm.js and ns.html are served from, without a trailing slash.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getGtmBaseUrl(?int $storeId = null): string
    {
        $url = rtrim(trim((string)$this->getValue(self::XML_GTM_BASE_URL, $storeId)), '/');

        return $url === '' ? self::DEFAULT_GTM_BASE_URL : $url;
    }

    /**
     * Host part of the gtm.js base URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getGtmHost(?int $storeId = null): string
    {
        return strtolower((string)parse_url($this->getGtmBaseUrl($storeId), PHP_URL_HOST));
    }

    /**
     * Hosts the container's tags load from or send to, added to the storefront CSP.
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getAdditionalCspHosts(?int $storeId = null): array
    {
        $hosts = $this->hostList->parse((string)$this->getValue(self::XML_CSP_HOSTS, $storeId));

        return array_values(array_filter($hosts, fn (string $host): bool => $this->hostList->isValid($host, true)));
    }

    /**
     * Whether the page nonce is pushed to the dataLayer as cspNonce for Custom HTML tags.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isNonceExposed(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_EXPOSE_NONCE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Consent Mode source: off, Magento's cookie notice, or an external consent platform.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getConsentMode(?int $storeId = null): string
    {
        $mode = (string)$this->getValue(self::XML_CONSENT_MODE, $storeId);

        return in_array($mode, ConsentMode::MODES, true) ? $mode : ConsentMode::OFF;
    }

    /**
     * Whether consent defaults to granted outside the EEA, the UK and Switzerland.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isGrantedElsewhere(?int $storeId = null): bool
    {
        return $this->getValue(self::XML_CONSENT_DEFAULT_ELSEWHERE, $storeId) !== 'denied';
    }

    /**
     * Milliseconds tags wait for a consent update before firing, clamped to 0–10000.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getWaitForUpdate(?int $storeId = null): int
    {
        $wait = (int)$this->getValue(self::XML_CONSENT_WAIT, $storeId);

        return max(0, min(self::WAIT_FOR_UPDATE_MAX, $wait));
    }

    /**
     * Whether ad click identifiers are redacted while ad_storage is denied.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAdsDataRedaction(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ADS_DATA_REDACTION, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether ad click information is passed through URLs while cookies are denied.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUrlPassthrough(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_URL_PASSTHROUGH, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Trim and upper-case a container id, the form it is stored and compared in.
     *
     * @param string $id
     * @return string
     */
    public function normalizeContainerId(string $id): string
    {
        return strtoupper(trim($id));
    }

    /**
     * Store-scope value of a path.
     *
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    private function getValue(string $path, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
