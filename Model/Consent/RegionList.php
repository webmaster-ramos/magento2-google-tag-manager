<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model\Consent;

/**
 * Regions where every consent signal starts denied: the EEA, the United Kingdom and Switzerland.
 *
 * Google's EU user consent policy covers exactly these for Ads measurement and
 * personalisation; the list is fixed, not configurable, so a store cannot ship granted
 * defaults to visitors the policy protects.
 */
class RegionList
{
    /**
     * ISO 3166-1 alpha-2: the 27 EU member states, Iceland, Liechtenstein, Norway, GB, CH.
     */
    public const CODES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE',
        'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        'IS', 'LI', 'NO',
        'GB', 'CH',
    ];

    /**
     * Region codes for the denied consent default.
     *
     * @return string[]
     */
    public function getCodes(): array
    {
        return self::CODES;
    }
}
