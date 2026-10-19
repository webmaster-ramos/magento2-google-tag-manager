<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Model\Config;
use WebRamos\GoogleTagManager\Model\Config\Backend\Enabled;
use WebRamos\GoogleTagManager\Model\HostList;

class EnabledTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    private Enabled $model;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->model = (new ObjectManager($this))->getObject(Enabled::class, [
            'config' => $this->scopeConfig,
            'moduleConfig' => new Config($this->createMock(ScopeConfigInterface::class), new HostList()),
            'hostList' => new HostList(),
        ]);
    }

    public function testDisablingNeedsNothing(): void
    {
        $this->scopeConfig->expects($this->never())->method('getValue');
        $this->model->setValue('0');

        $this->model->beforeSave();
        $this->assertSame('0', $this->model->getValue());
    }

    public function testEnablingWithSubmittedIdAndHostsPasses(): void
    {
        $this->scopeConfig->expects($this->never())->method('getValue');
        $this->model->setValue('1');
        $this->model->setData('fieldset_data', ['container_id' => 'gtm-abc123', 'allowed_hosts' => 'shop.example']);

        $this->model->beforeSave();
        $this->assertSame('1', $this->model->getValue());
    }

    public function testEnablingWithoutAContainerIdIsRefused(): void
    {
        $this->model->setValue('1');
        $this->model->setData('fieldset_data', ['container_id' => '', 'allowed_hosts' => 'shop.example']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('container ID');
        $this->model->beforeSave();
    }

    public function testInheritedIdIsReadAtTheSavedScopeAndHostsAtDefault(): void
    {
        $this->model->setValue('1');
        $this->model->setData('fieldset_data', ['container_id' => null]);
        $this->model->setData('scope', 'stores');
        $this->model->setData('scope_id', 3);
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path, string $scope = 'default', $code = null) => match ([$path, $scope, $code]) {
                [Config::XML_CONTAINER_ID, 'stores', 3] => 'GTM-ABC123',
                [Config::XML_ALLOWED_HOSTS, 'default', null] => "shop.example",
                default => null,
            }
        );

        $this->model->beforeSave();
        $this->assertSame('1', $this->model->getValue());
    }

    public function testEnablingWithoutHostsIsRefused(): void
    {
        $this->model->setValue('1');
        $this->model->setData('fieldset_data', ['container_id' => 'GTM-ABC123']);
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Allowed Hosts');
        $this->model->beforeSave();
    }
}
