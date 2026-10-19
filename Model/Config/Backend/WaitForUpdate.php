<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use WebRamos\GoogleTagManager\Model\Config;

/**
 * Keeps wait_for_update a whole number of milliseconds in 0–10000.
 */
class WaitForUpdate extends Value
{
    /**
     * Refuse anything outside 0–10000 whole milliseconds.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string)$this->getValue());
        if (!preg_match('/^\d+$/', $value) || (int)$value > Config::WAIT_FOR_UPDATE_MAX) {
            throw new LocalizedException(__(
                'Wait for Update must be a whole number of milliseconds from 0 to %1.',
                Config::WAIT_FOR_UPDATE_MAX
            ));
        }
        $this->setValue((string)(int)$value);

        return parent::beforeSave();
    }
}
