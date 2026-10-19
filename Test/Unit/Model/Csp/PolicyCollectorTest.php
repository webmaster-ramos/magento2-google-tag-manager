<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\Model\Csp;

use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Csp\Model\Policy\FetchPolicyFactory;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Model\Config;
use WebRamos\GoogleTagManager\Model\Csp\PolicyCollector;
use WebRamos\GoogleTagManager\Model\HostGuard;

class PolicyCollectorTest extends TestCase
{
    private Config&MockObject $config;

    private HostGuard&MockObject $hostGuard;

    private State&MockObject $state;

    private PolicyCollector $collector;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->hostGuard = $this->createMock(HostGuard::class);
        $this->state = $this->createMock(State::class);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $factory = $this->createMock(FetchPolicyFactory::class);
        $factory->method('create')->willReturnCallback(
            fn (array $args) => new FetchPolicy($args['id'], $args['noneAllowed'], $args['hostSources'])
        );

        $this->collector = new PolicyCollector($this->config, $this->hostGuard, $storeManager, $factory, $this->state);
    }

    private function existing(): array
    {
        return [new FetchPolicy('script-src', false, [], [], true)];
    }

    public function testOutsideTheStorefrontNothingIsAdded(): void
    {
        $this->state->method('getAreaCode')->willReturn('adminhtml');
        $this->config->expects($this->never())->method('isEnabled');

        $this->assertEquals($this->existing(), $this->collector->collect($this->existing()));
    }

    public function testWithoutAnAreaNothingIsAdded(): void
    {
        $this->state->method('getAreaCode')->willThrowException(new LocalizedException(__('Area code is not set')));

        $this->assertEquals($this->existing(), $this->collector->collect($this->existing()));
    }

    public function testDisabledStoreKeepsTheStockPolicy(): void
    {
        $this->state->method('getAreaCode')->willReturn('frontend');
        $this->config->method('isEnabled')->willReturn(false);

        $this->assertEquals($this->existing(), $this->collector->collect($this->existing()));
    }

    public function testGuardedHostKeepsTheStockPolicy(): void
    {
        $this->state->method('getAreaCode')->willReturn('frontend');
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getContainerId')->willReturn('GTM-ABC123');
        $this->hostGuard->method('isAllowed')->willReturn(false);

        $this->assertEquals($this->existing(), $this->collector->collect($this->existing()));
    }

    public function testAddsHostsAfterTheExistingPolicies(): void
    {
        $this->state->method('getAreaCode')->willReturn('frontend');
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getContainerId')->willReturn('GTM-ABC123');
        $this->hostGuard->method('isAllowed')->willReturn(true);
        $this->config->method('getGtmHost')->willReturn('www.googletagmanager.com');
        $this->config->method('getAdditionalCspHosts')
            ->willReturn(['connect.facebook.net', 'www.googletagmanager.com']);

        $policies = $this->collector->collect($this->existing());

        $this->assertCount(5, $policies);
        $this->assertInstanceOf(FetchPolicy::class, $policies[0]);
        $this->assertTrue($policies[0]->isSelfAllowed(), 'existing policies come first, untouched');
        $byId = [];
        foreach (array_slice($policies, 1) as $policy) {
            $this->assertInstanceOf(FetchPolicy::class, $policy);
            $this->assertFalse($policy->isNoneAllowed());
            $byId[$policy->getId()] = $policy->getHostSources();
        }
        $both = ['www.googletagmanager.com', 'connect.facebook.net'];
        $this->assertSame($both, $byId['script-src']);
        $this->assertSame($both, $byId['connect-src']);
        $this->assertSame($both, $byId['img-src']);
        $this->assertSame(['www.googletagmanager.com'], $byId['frame-src']);
    }
}
