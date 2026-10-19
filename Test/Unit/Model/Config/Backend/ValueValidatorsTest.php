<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Model\Config;
use WebRamos\GoogleTagManager\Model\Config\Backend\AllowedHosts;
use WebRamos\GoogleTagManager\Model\Config\Backend\ContainerId;
use WebRamos\GoogleTagManager\Model\Config\Backend\CspHosts;
use WebRamos\GoogleTagManager\Model\Config\Backend\GtmBaseUrl;
use WebRamos\GoogleTagManager\Model\Config\Backend\WaitForUpdate;
use WebRamos\GoogleTagManager\Model\HostList;

/**
 * The five single-field validators: each normalises what it accepts and names what it refuses.
 */
class ValueValidatorsTest extends TestCase
{
    private function model(string $class): Value
    {
        return (new ObjectManager($this))->getObject($class, [
            'hostList' => new HostList(),
            'moduleConfig' => new Config($this->createMock(ScopeConfigInterface::class), new HostList()),
        ]);
    }

    private function save(string $class, string $value): string
    {
        $model = $this->model($class);
        $model->setValue($value);
        $model->beforeSave();

        return (string)$model->getValue();
    }

    /**
     * @dataProvider accepted
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('accepted')]
    public function testAcceptedAndNormalised(string $class, string $input, string $stored): void
    {
        $this->assertSame($stored, $this->save($class, $input));
    }

    public static function accepted(): array
    {
        return [
            'container id normalised' => [ContainerId::class, ' gtm-abc123 ', 'GTM-ABC123'],
            'container id may be empty' => [ContainerId::class, '', ''],
            'allowed hosts one per line' => [AllowedHosts::class, 'Shop.Example, www.shop.example', "shop.example\nwww.shop.example"],
            'allowed hosts may be empty' => [AllowedHosts::class, '', ''],
            'csp hosts with wildcard' => [CspHosts::class, "connect.facebook.net\n*.Clarity.ms", "connect.facebook.net\n*.clarity.ms"],
            'base url default' => [GtmBaseUrl::class, 'https://www.googletagmanager.com', 'https://www.googletagmanager.com'],
            'base url with path, slash stripped' => [GtmBaseUrl::class, 'https://shop.example/metrics/', 'https://shop.example/metrics'],
            'empty base url restores google' => [GtmBaseUrl::class, '  ', Config::DEFAULT_GTM_BASE_URL],
            'wait zero' => [WaitForUpdate::class, '0', '0'],
            'wait max' => [WaitForUpdate::class, '10000', '10000'],
            'wait leading zeros' => [WaitForUpdate::class, '0500', '500'],
        ];
    }

    /**
     * @dataProvider refused
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refused')]
    public function testRefused(string $class, string $input, string $named): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($named);
        $this->save($class, $input);
    }

    public static function refused(): array
    {
        return [
            'universal analytics id' => [ContainerId::class, 'UA-1234-1', 'UA-1234-1'],
            'ga4 id' => [ContainerId::class, 'G-ABC123', 'G-ABC123'],
            'allowed host with scheme' => [AllowedHosts::class, 'https://shop.example', 'https://shop.example'],
            'allowed host wildcard' => [AllowedHosts::class, '*.shop.example', '*.shop.example'],
            'csp bare star' => [CspHosts::class, 'good.example *', '"*.": *'],
            'csp keyword' => [CspHosts::class, "'unsafe-inline'", "'unsafe-inline'"],
            'csp scheme source' => [CspHosts::class, 'https:', 'https:'],
            'csp data' => [CspHosts::class, 'data:', 'data:'],
            'base url http' => [GtmBaseUrl::class, 'http://shop.example', 'http://shop.example'],
            'base url port' => [GtmBaseUrl::class, 'https://shop.example:8443', 'https://shop.example:8443'],
            'base url query' => [GtmBaseUrl::class, 'https://shop.example/?a=1', 'https://shop.example/?a=1'],
            'base url credentials' => [GtmBaseUrl::class, 'https://u:p@shop.example', 'https://u:p@shop.example'],
            'base url script' => [GtmBaseUrl::class, 'javascript:alert(1)', 'javascript:alert(1)'],
            'base url quote in path' => [GtmBaseUrl::class, 'https://shop.example/a"b', 'https://shop.example/a"b'],
            'wait too large' => [WaitForUpdate::class, '10001', '10000'],
            'wait negative' => [WaitForUpdate::class, '-1', '10000'],
            'wait not a number' => [WaitForUpdate::class, 'soon', '10000'],
        ];
    }
}
