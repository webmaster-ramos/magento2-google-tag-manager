<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;
use Magento\Framework\Module\PackageInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Block\Adminhtml\System\Config\SupportBanner;

/**
 * Field subclass renders the vendor banner row plus a derived identity line.
 *
 * The identity line's two failure paths — an empty version and a throwing
 * PackageInfo — are provable HERE and nowhere else: neither can be provoked
 * in a browser without corrupting a package's composer.json.
 *
 * @covers \WebRamos\GoogleTagManager\Block\Adminhtml\System\Config\SupportBanner
 */
class SupportBannerTest extends TestCase
{
    private const HTML_ID = 'webramos_structured_logging_general_support_banner';

    /** @var PackageInfo&MockObject */
    private PackageInfo $packageInfo;

    /** @var Escaper&MockObject */
    private Escaper $escaper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->packageInfo = $this->createMock(PackageInfo::class);
        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnArgument(0);
        $this->escaper->method('escapeHtmlAttr')->willReturnArgument(0);
    }

    public function testRendersTheModuleIdentifier(): void
    {
        $this->packageInfo->method('getVersion')->willReturn('0.1.0');

        $this->assertStringContainsString('WebRamos_GoogleTagManager', $this->renderBanner());
    }

    public function testRendersTheVersionAfterTheIdentifierSeparatedByOneSpace(): void
    {
        $this->packageInfo->method('getVersion')->willReturn('0.1.0');

        $this->assertSame('WebRamos_GoogleTagManager 0.1.0', $this->identityLine($this->renderBanner()));
    }

    public function testOmitsTheVersionCleanlyWhenPackageInfoReturnsEmptyString(): void
    {
        $this->packageInfo->method('getVersion')->willReturn('');

        $html = $this->renderBanner();

        // No trailing space, no separator, no label — the identifier alone.
        $this->assertSame('WebRamos_GoogleTagManager', $this->identityLine($html));
        $this->assertStringNotContainsString('version', $html);
    }

    public function testFallsBackToIdentifierOnlyWhenPackageInfoThrows(): void
    {
        $this->packageInfo->method('getVersion')->willThrowException(
            new \InvalidArgumentException('Foo_Bar composer.json error: Syntax error')
        );

        // A third party's malformed composer.json must not escape render().
        $html = $this->renderBanner();

        $this->assertSame('WebRamos_GoogleTagManager', $this->identityLine($html));
    }

    public function testPreservesTheRowAnchorTheBannerClassAndAllThreeLinks(): void
    {
        $this->packageInfo->method('getVersion')->willReturn('0.1.0');

        $html = $this->renderBanner();

        $this->assertStringContainsString('<tr id="row_' . self::HTML_ID . '">', $html);
        $this->assertStringContainsString('class="webramos-support-banner"', $html);
        $this->assertStringContainsString('href="https://webmaster-ramos.com"', $html);
        $this->assertStringContainsString('href="mailto:contact@webmaster-ramos.com"', $html);
        $this->assertStringContainsString('href="https://webmaster-ramos.com/support"', $html);
    }

    /**
     * Text content of the identity element, asserted on alone.
     *
     * The whole banner's text also contains webmaster-ramos.com, so a regex
     * over it would match by accident; and only an exact comparison on this
     * element can prove the absence of a trailing space.
     */
    private function identityLine(string $html): string
    {
        $matched = preg_match(
            '#<div class="webramos-support-banner-identity"[^>]*>(.*?)</div>#s',
            $html,
            $matches
        );
        $this->assertSame(1, $matched, 'identity element is absent from the rendered banner');

        return $matches[1];
    }

    /**
     * Build the block without its constructor and invoke the public render().
     *
     * newInstanceWithoutConstructor() because Magento's Field -> Template ->
     * AbstractBlock parent chain calls ObjectManager::getInstance() inside
     * __construct(), fatal under PHPUnit. Two collaborators are injected by
     * reflection: $_escaper (AbstractBlock, read through the inherited
     * escapeHtml()/escapeHtmlAttr() helpers) and $packageInfo (own, promoted
     * readonly — reflection sets it fine on an uninitialised instance).
     * getModuleName() needs NO injection: it derives from get_class($this)
     * via AbstractBlock::extractModuleName() and caches through setData(),
     * whose $_data has an array default.
     */
    private function renderBanner(): string
    {
        $block = (new \ReflectionClass(SupportBanner::class))->newInstanceWithoutConstructor();

        // No setAccessible() call: it has been a no-op since PHP 8.1 (this fleet's floor)
        // and is DEPRECATED in 8.5, where Magento's unit bootstrap turns the deprecation
        // into an exception — the suite would error on the runtime Adobe QA runs on.
        $escaperProp = new \ReflectionProperty(\Magento\Framework\View\Element\AbstractBlock::class, '_escaper');
        $escaperProp->setValue($block, $this->escaper);

        $packageInfoProp = new \ReflectionProperty(SupportBanner::class, 'packageInfo');
        $packageInfoProp->setValue($block, $this->packageInfo);

        $element = $this->createMock(AbstractElement::class);
        $element->method('getHtmlId')->willReturn(self::HTML_ID);

        return $block->render($element);
    }
}
