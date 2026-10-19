<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model;

use Magento\Store\Model\StoreManagerInterface;

/**
 * Staging guard: the container may load only where the store's base URL host is listed.
 *
 * Staging and local environments run on copies of the production database, so the
 * container id travels with the data; the host list keeps their test traffic and test
 * orders out of the production analytics and ads accounts. The configured base URL is
 * compared, never the request Host header, which a client controls. An empty list
 * allows nothing.
 */
class HostGuard
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Whether the store's base URL host is on the allowed list (exact, case-insensitive).
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAllowed(?int $storeId = null): bool
    {
        $allowed = $this->config->getAllowedHosts();
        if (!$allowed) {
            return false;
        }

        $baseUrl = (string)$this->storeManager->getStore($storeId)->getBaseUrl();
        $host = strtolower((string)parse_url($baseUrl, PHP_URL_HOST));

        return $host !== '' && in_array($host, $allowed, true);
    }
}
