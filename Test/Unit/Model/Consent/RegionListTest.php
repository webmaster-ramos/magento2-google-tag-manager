<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Test\Unit\Model\Consent;

use PHPUnit\Framework\TestCase;
use WebRamos\GoogleTagManager\Model\Consent\RegionList;

class RegionListTest extends TestCase
{
    public function testCoversEeaUkAndSwitzerland(): void
    {
        $codes = (new RegionList())->getCodes();

        $this->assertCount(32, $codes);
        $this->assertSame($codes, array_values(array_unique($codes)));
        foreach (['DE', 'FR', 'ES', 'IS', 'LI', 'NO', 'GB', 'CH'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertNotContains('US', $codes);
    }
}
