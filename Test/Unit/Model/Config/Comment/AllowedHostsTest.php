<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\Model\Config\Comment;

use Magento\Framework\Escaper;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Model\Config\Comment\AllowedHosts;

class AllowedHostsTest extends TestCase
{
    private function comment(array $baseUrls): string
    {
        $stores = array_map(function (string $url) {
            $store = $this->createMock(Store::class);
            $store->method('getBaseUrl')->willReturn($url);

            return $store;
        }, $baseUrls);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(fn ($v) => htmlspecialchars((string)$v));

        return (new AllowedHosts($storeManager, $escaper))->getCommentText('');
    }

    public function testNamesEachBaseUrlHostOnce(): void
    {
        $text = $this->comment(['https://shop.example/', 'https://SHOP.example/', 'https://b2b.shop.example/']);

        $this->assertStringContainsString('Base URL hosts of this installation: shop.example, b2b.shop.example.', $text);
        $this->assertStringContainsString('Empty: the container loads nowhere.', $text);
    }

    public function testWithoutStoresOnlyTheExplanation(): void
    {
        $this->assertStringNotContainsString('Base URL hosts', $this->comment([]));
    }
}
