<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\Model;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Model\Config;
use WebRamos\GoogleTagManager\Model\HostGuard;

class HostGuardTest extends TestCase
{
    private function guard(array $allowed, string $baseUrl): HostGuard
    {
        $config = $this->createMock(Config::class);
        $config->method('getAllowedHosts')->willReturn($allowed);
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn($baseUrl);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new HostGuard($config, $storeManager);
    }

    public function testEmptyListAllowsNothing(): void
    {
        $this->assertFalse($this->guard([], 'https://shop.example/')->isAllowed(1));
    }

    public function testListedHostIsAllowed(): void
    {
        $this->assertTrue($this->guard(['shop.example'], 'https://shop.example/')->isAllowed(1));
    }

    public function testHostMatchIsCaseInsensitive(): void
    {
        $this->assertTrue($this->guard(['shop.example'], 'https://SHOP.example/')->isAllowed(1));
    }

    public function testUnlistedHostIsRefused(): void
    {
        $this->assertFalse($this->guard(['shop.example'], 'https://staging.shop.example/')->isAllowed(1));
    }

    public function testSubdomainIsNotImplied(): void
    {
        $this->assertFalse($this->guard(['shop.example'], 'https://www.shop.example/')->isAllowed(1));
    }

    public function testBaseUrlWithoutHostIsRefused(): void
    {
        $this->assertFalse($this->guard(['shop.example'], '/relative/')->isAllowed(1));
    }
}
