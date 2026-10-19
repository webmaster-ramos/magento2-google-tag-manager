<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model\Config\Comment;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Comment under Allowed Hosts that names the base URL hosts this installation actually has.
 */
class AllowedHosts implements CommentInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getCommentText($elementValue)
    {
        $text = (string)__(
            'Production hosts, one per line. The container loads only when the store\'s base URL host is '
            . 'listed, so staging and local copies of this database send nothing. Do not list staging or '
            . 'local hosts. Empty: the container loads nowhere.'
        );

        $hosts = [];
        foreach ($this->storeManager->getStores() as $store) {
            $host = strtolower((string)parse_url((string)$store->getBaseUrl(), PHP_URL_HOST));
            if ($host !== '') {
                $hosts[$host] = true;
            }
        }
        if ($hosts) {
            $text .= ' ' . (string)__(
                'Base URL hosts of this installation: %1.',
                $this->escaper->escapeHtml(implode(', ', array_keys($hosts)))
            );
        }

        return $text;
    }
}
