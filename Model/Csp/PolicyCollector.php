<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model\Csp;

use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicyFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use WebRamos\GoogleTagManager\Model\Config;
use WebRamos\GoogleTagManager\Model\HostGuard;

/**
 * Adds the container's hosts to the storefront Content Security Policy.
 *
 * The gtm.js host and the merchant's "Additional Tag Hosts" go into script-src, connect-src
 * and img-src; the gtm.js host also into frame-src (ns.html). Only where the container
 * actually renders, so a disabled or guarded store keeps the stock policy. On the checkout
 * this is what lets tags inside the container load under restrict mode without a code change.
 *
 * Registered in the global etc/di.xml, not etc/frontend/di.xml: an area di.xml replaces a
 * global array argument as a whole (ObjectManager Config::extend() uses array_replace per
 * argument), so a frontend "collectors" item would drop every core collector and leave a
 * checkout policy with nothing but these hosts. The storefront-only rule lives here instead.
 */
class PolicyCollector implements PolicyCollectorInterface
{
    private const HOST_DIRECTIVES = ['script-src', 'connect-src', 'img-src'];

    public function __construct(
        private readonly Config $config,
        private readonly HostGuard $hostGuard,
        private readonly StoreManagerInterface $storeManager,
        private readonly FetchPolicyFactory $fetchPolicyFactory,
        private readonly State $appState
    ) {
    }

    /**
     * @inheritdoc
     */
    public function collect(array $defaultPolicies = []): array
    {
        if (!$this->isStorefront()) {
            return $defaultPolicies;
        }

        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)
            || $this->config->getContainerId($storeId) === null
            || !$this->hostGuard->isAllowed($storeId)
        ) {
            return $defaultPolicies;
        }

        $gtmHost = $this->config->getGtmHost($storeId);
        $hosts = array_values(array_unique(array_filter(
            array_merge([$gtmHost], $this->config->getAdditionalCspHosts($storeId))
        )));
        if (!$hosts) {
            return $defaultPolicies;
        }

        $policies = $defaultPolicies;
        foreach (self::HOST_DIRECTIVES as $directive) {
            $policies[] = $this->fetchPolicyFactory->create([
                'id' => $directive,
                'noneAllowed' => false,
                'hostSources' => $hosts,
            ]);
        }
        if ($gtmHost !== '') {
            $policies[] = $this->fetchPolicyFactory->create([
                'id' => 'frame-src',
                'noneAllowed' => false,
                'hostSources' => [$gtmHost],
            ]);
        }

        return $policies;
    }

    /**
     * Whether the current request is a storefront one.
     *
     * @return bool
     */
    private function isStorefront(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND;
        } catch (LocalizedException) {
            return false;
        }
    }
}
