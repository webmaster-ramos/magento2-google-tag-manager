<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Default consent state outside the regions where it is always denied.
 */
class ConsentDefault implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'granted', 'label' => __('Granted')],
            ['value' => 'denied', 'label' => __('Denied')],
        ];
    }
}
