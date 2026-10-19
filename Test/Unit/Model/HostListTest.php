<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Model\HostList;

class HostListTest extends TestCase
{
    private HostList $hostList;

    protected function setUp(): void
    {
        $this->hostList = new HostList();
    }

    public function testParseSplitsOnCommasSpacesAndNewlinesAndLowercases(): void
    {
        $this->assertSame(
            ['shop.example', 'www.shop.example', 'cdn.example'],
            $this->hostList->parse("Shop.Example, www.shop.example\n\n  cdn.example  shop.example")
        );
    }

    public function testParseOfEmptyOrNullIsEmpty(): void
    {
        $this->assertSame([], $this->hostList->parse(null));
        $this->assertSame([], $this->hostList->parse(" \n , "));
    }

    /**
     * @dataProvider validHosts
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validHosts')]
    public function testValidHosts(string $host, bool $wildcard): void
    {
        $this->assertTrue($this->hostList->isValid($host, $wildcard));
    }

    public static function validHosts(): array
    {
        return [
            'plain' => ['shop.example', false],
            'single label' => ['localhost', false],
            'hyphen and digits' => ['my-shop2.co.uk', false],
            'punycode' => ['xn--80ak6aa92e.com', false],
            'wildcard when allowed' => ['*.clarity.ms', true],
        ];
    }

    /**
     * @dataProvider invalidHosts
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidHosts')]
    public function testInvalidHosts(string $host, bool $wildcard): void
    {
        $this->assertFalse($this->hostList->isValid($host, $wildcard));
    }

    public static function invalidHosts(): array
    {
        return [
            'empty' => ['', true],
            'bare star' => ['*', true],
            'wildcard when not allowed' => ['*.shop.example', false],
            'scheme' => ['https://shop.example', true],
            'scheme keyword' => ['https:', true],
            'data scheme' => ['data:', true],
            'quoted keyword' => ["'unsafe-inline'", true],
            'path' => ['shop.example/path', true],
            'port' => ['shop.example:8080', true],
            'leading hyphen label' => ['-shop.example', true],
            'trailing dot label' => ['shop..example', true],
            'too long label' => [str_repeat('a', 64) . '.example', true],
            'too long host' => [str_repeat('a.', 127) . 'com', true],
        ];
    }

    public function testInvalidReturnsOnlyTheBadEntries(): void
    {
        $this->assertSame(
            ['https://bad.example', '*'],
            $this->hostList->invalid(['good.example', 'https://bad.example', '*', '*.ok.example'], true)
        );
    }
}
