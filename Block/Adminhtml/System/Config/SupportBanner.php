<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Module\PackageInfo;

/**
 * Renders the WebRamos vendor banner as a full-width row at the end of
 * the system_config group it lives in. Right-aligned so it reads like
 * a footer credit; non-functional (no input, no submit).
 *
 * Wired via `<frontend_model>` on a dummy `<field id="support_banner">`
 * with high `sortOrder`. See docs/extensions/PATTERNS.md Pattern 1.
 *
 * The identity line under the links carries the module identifier and its
 * package version — the string a merchant pastes into a support ticket, and
 * the only thing on the configuration pane that names the module at all (the
 * section label renders in the left navigation, not in the pane). Both halves
 * are DERIVED, never written down: the identifier from AbstractBlock's
 * getModuleName(), which slices the class name at the first \Block\ and so is
 * unaffected by an \Interceptor suffix, and the version from PackageInfo. That
 * is what lets this file copy into a new module with only the namespace changed.
 *
 * Never throws; never renders empty; never renders a half-line.
 * PackageInfo::getVersion() returns '' — not null, no exception — for a package
 * whose composer.json carries no `version` key, which is ordinary for a
 * VCS-distributed checkout; the identifier then renders alone, with no
 * separator and no placeholder. PackageInfo::load() additionally rethrows
 * \InvalidArgumentException when ANY enabled module ships invalid JSON in its
 * composer.json, and Field::render() has nothing above it to catch: a third
 * party's malformed file must not take down this configuration page.
 *
 * CRITICAL: inject PackageInfo, NOT PackageInfoFactory. The factory builds a
 * fresh instance over a fresh Dir\Reader scoped to FullModuleList on every
 * create() call and scans disabled modules; core uses it only in Setup paths.
 * And never default-instantiate a constructor argument (`= new PackageInfo()`):
 * it is var_export'd into generated/metadata/global.php and fatals at
 * bootstrap. See KB `magento-di-default-arg-var-export-landmine` (HIGH).
 */
class SupportBanner extends Field
{
    public function __construct(
        Context $context,
        private readonly PackageInfo $packageInfo,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element): string
    {
        return '<tr id="row_' . $this->escapeHtmlAttr($element->getHtmlId()) . '">'
            . '<td colspan="5">'
            . '<div class="webramos-support-banner" '
            .   'style="text-align:right;margin-top:4px;padding:8px 0;font-size:1.2rem;">'
            . '<a href="https://webmaster-ramos.com" target="_blank">'
            .   '<strong>Webmaster Ramos</strong></a>'
            . ' &nbsp;|&nbsp; '
            . '<a href="mailto:contact@webmaster-ramos.com">contact@webmaster-ramos.com</a>'
            . ' &nbsp;|&nbsp; '
            . '<a href="https://webmaster-ramos.com/support" target="_blank">Support</a>'
            . '<div class="webramos-support-banner-identity" '
            .   'style="margin-top:2px;color:#777;font-size:1.1rem;">'
            . $this->getIdentityLine()
            . '</div>'
            . '</div>'
            . '</td>'
            . '</tr>';
    }

    /**
     * Module identifier, plus the package version when one is resolvable.
     *
     * Escaping happens HERE, on each value, and never on render()'s assembled
     * string — escaping the return would turn `<tr>` into `&lt;tr&gt;` and
     * print the markup.
     *
     * @return string
     */
    private function getIdentityLine(): string
    {
        $module = (string) $this->getModuleName();

        try {
            $version = (string) $this->packageInfo->getVersion($module);
        } catch (\Throwable) {
            // A third party's malformed composer.json must not break this page.
            $version = '';
        }

        return $version === ''
            ? $this->escapeHtml($module)
            : $this->escapeHtml($module) . ' ' . $this->escapeHtml($version);
    }
}
