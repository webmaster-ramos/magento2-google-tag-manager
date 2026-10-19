<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\ViewModel;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Model\Config;
use WebRamos\GoogleTagManager\Model\Consent\RegionList;
use WebRamos\GoogleTagManager\Model\HostGuard;
use WebRamos\GoogleTagManager\ViewModel\Loader;

class LoaderTest extends TestCase
{
    private Config&MockObject $config;

    private HostGuard&MockObject $hostGuard;

    private File&MockObject $file;

    private Reader&MockObject $reader;

    private Loader $loader;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->hostGuard = $this->createMock(HostGuard::class);
        $this->file = $this->createMock(File::class);
        $this->reader = $this->createMock(Reader::class);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(2);
        $store->method('getWebsiteId')->willReturn(5);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->loader = new Loader(
            $this->config,
            $this->hostGuard,
            $storeManager,
            new RegionList(),
            new Json(),
            $this->reader,
            $this->file
        );
    }

    private function renderable(string $consentMode = 'off'): void
    {
        $this->config->method('isEnabled')->with(2)->willReturn(true);
        $this->config->method('getContainerId')->willReturn('GTM-ABC123');
        $this->hostGuard->method('isAllowed')->with(2)->willReturn(true);
        $this->config->method('getGtmBaseUrl')->willReturn('https://www.googletagmanager.com');
        $this->config->method('isNonceExposed')->willReturn(true);
        $this->config->method('getConsentMode')->willReturn($consentMode);
        $this->config->method('isGrantedElsewhere')->willReturn(false);
        $this->config->method('getWaitForUpdate')->willReturn(500);
        $this->config->method('isAdsDataRedaction')->willReturn(true);
        $this->config->method('isUrlPassthrough')->willReturn(false);
    }

    public function testNothingRendersWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->hostGuard->expects($this->never())->method('isAllowed');

        $this->assertNull($this->loader->getSettings());
        $this->assertNull($this->loader->getNoscriptUrl());
    }

    public function testNothingRendersWithoutAValidContainerId(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getContainerId')->willReturn(null);

        $this->assertNull($this->loader->getSettings());
    }

    public function testNothingRendersOnAHostOffTheList(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getContainerId')->willReturn('GTM-ABC123');
        $this->hostGuard->method('isAllowed')->willReturn(false);

        $this->assertNull($this->loader->getSettings());
        $this->assertNull($this->loader->getNoscriptUrl());
    }

    public function testSettingsWithConsentOff(): void
    {
        $this->renderable();

        $this->assertSame([
            'src' => 'https://www.googletagmanager.com/gtm.js?id=GTM-ABC123',
            'noscript' => 'https://www.googletagmanager.com/ns.html?id=GTM-ABC123',
            'exposeNonce' => true,
            'consent' => null,
        ], $this->loader->getSettings());
        $this->assertSame('https://www.googletagmanager.com/ns.html?id=GTM-ABC123', $this->loader->getNoscriptUrl());
    }

    public function testMagentoNoticeModeCarriesTheCookieAndWebsite(): void
    {
        $this->renderable('magento');

        $consent = $this->loader->getSettings()['consent'];
        $this->assertSame('magento', $consent['mode']);
        $this->assertCount(32, $consent['regions']);
        $this->assertSame('denied', $consent['elsewhere']);
        $this->assertSame(500, $consent['waitForUpdate']);
        $this->assertTrue($consent['adsDataRedaction']);
        $this->assertFalse($consent['urlPassthrough']);
        $this->assertSame('user_allowed_save_cookie', $consent['cookie']);
        $this->assertSame(5, $consent['websiteId']);
    }

    public function testCmpModeSetsDefaultsOnly(): void
    {
        $this->renderable('cmp');

        $consent = $this->loader->getSettings()['consent'];
        $this->assertSame('cmp', $consent['mode']);
        $this->assertArrayNotHasKey('cookie', $consent);
        $this->assertArrayNotHasKey('websiteId', $consent);
    }

    public function testJsonLeavesTheNoscriptUrlOut(): void
    {
        $this->renderable();

        $decoded = json_decode($this->loader->getSettingsJson(), true);
        $this->assertArrayNotHasKey('noscript', $decoded);
        $this->assertSame('https://www.googletagmanager.com/gtm.js?id=GTM-ABC123', $decoded['src']);
    }

    public function testResolvedOncePerRequest(): void
    {
        $this->config->expects($this->once())->method('isEnabled')->willReturn(false);

        $this->loader->getSettings();
        $this->loader->getNoscriptUrl();
        $this->loader->getSettingsJson();
    }

    public function testLoaderScriptDropsTheFileHeaderAndIsReadOnce(): void
    {
        $this->reader->method('getModuleDir')->willReturn('/module/view');
        $this->file->expects($this->once())->method('fileGetContents')
            ->with('/module/view/frontend/web/js/loader.js')
            ->willReturn("/**\n * Copyright\n */\n(function () {\n    'use strict';\n})();\n");

        $this->assertSame("(function () {\n    'use strict';\n})();", $this->loader->getLoaderScript());
        $this->loader->getLoaderScript();
    }
}
