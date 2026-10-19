<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Model\Config;
use WebRamos\GoogleTagManager\Model\HostList;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig, new HostList());
    }

    private function storeValue(string $path, mixed $value): void
    {
        $this->scopeConfig->method('getValue')
            ->willReturnCallback(fn (string $p) => $p === $path ? $value : null);
    }

    public function testContainerIdIsNormalised(): void
    {
        $this->storeValue(Config::XML_CONTAINER_ID, '  gtm-abc123 ');

        $this->assertSame('GTM-ABC123', $this->config->getContainerId(1));
    }

    public function testInvalidContainerIdIsNull(): void
    {
        $this->storeValue(Config::XML_CONTAINER_ID, 'UA-12345-1');

        $this->assertNull($this->config->getContainerId(1));
    }

    public function testAllowedHostsAreReadAtDefaultScope(): void
    {
        $this->scopeConfig->expects($this->once())->method('getValue')
            ->with(Config::XML_ALLOWED_HOSTS)
            ->willReturn("Shop.Example\nwww.shop.example");

        $this->assertSame(['shop.example', 'www.shop.example'], $this->config->getAllowedHosts());
    }

    public function testGtmBaseUrlLosesTrailingSlash(): void
    {
        $this->storeValue(Config::XML_GTM_BASE_URL, 'https://shop.example/metrics/ ');

        $this->assertSame('https://shop.example/metrics', $this->config->getGtmBaseUrl(1));
        $this->assertSame('shop.example', $this->config->getGtmHost(1));
    }

    public function testEmptyGtmBaseUrlFallsBackToGoogle(): void
    {
        $this->storeValue(Config::XML_GTM_BASE_URL, '');

        $this->assertSame(Config::DEFAULT_GTM_BASE_URL, $this->config->getGtmBaseUrl(1));
        $this->assertSame('www.googletagmanager.com', $this->config->getGtmHost(1));
    }

    public function testAdditionalCspHostsDropInvalidEntries(): void
    {
        $this->storeValue(Config::XML_CSP_HOSTS, "connect.facebook.net\n*\n*.clarity.ms\n'unsafe-inline'");

        $this->assertSame(['connect.facebook.net', '*.clarity.ms'], $this->config->getAdditionalCspHosts(1));
    }

    public function testUnknownConsentModeIsOff(): void
    {
        $this->storeValue(Config::XML_CONSENT_MODE, 'something');

        $this->assertSame('off', $this->config->getConsentMode(1));
    }

    public function testKnownConsentModeIsReturned(): void
    {
        $this->storeValue(Config::XML_CONSENT_MODE, 'cmp');

        $this->assertSame('cmp', $this->config->getConsentMode(1));
    }

    public function testGrantedElsewhereUnlessDenied(): void
    {
        $this->storeValue(Config::XML_CONSENT_DEFAULT_ELSEWHERE, 'denied');
        $this->assertFalse($this->config->isGrantedElsewhere(1));

        $other = new Config($this->createMock(ScopeConfigInterface::class), new HostList());
        $this->assertTrue($other->isGrantedElsewhere(1));
    }

    /**
     * @dataProvider waitValues
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('waitValues')]
    public function testWaitForUpdateIsClamped(mixed $stored, int $expected): void
    {
        $this->storeValue(Config::XML_CONSENT_WAIT, $stored);

        $this->assertSame($expected, $this->config->getWaitForUpdate(1));
    }

    public static function waitValues(): array
    {
        return [
            'normal' => ['500', 500],
            'negative' => ['-5', 0],
            'too large' => ['99999', 10000],
            'missing' => [null, 0],
        ];
    }

    public function testFlagsReadStoreScope(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturnMap([
            [Config::XML_ENABLED, ScopeInterface::SCOPE_STORE, 3, true],
            [Config::XML_EXPOSE_NONCE, ScopeInterface::SCOPE_STORE, 3, false],
            [Config::XML_ADS_DATA_REDACTION, ScopeInterface::SCOPE_STORE, 3, true],
            [Config::XML_URL_PASSTHROUGH, ScopeInterface::SCOPE_STORE, 3, false],
        ]);

        $this->assertTrue($this->config->isEnabled(3));
        $this->assertFalse($this->config->isNonceExposed(3));
        $this->assertTrue($this->config->isAdsDataRedaction(3));
        $this->assertFalse($this->config->isUrlPassthrough(3));
    }
}
