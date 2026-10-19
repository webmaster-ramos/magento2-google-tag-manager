<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Where consent updates come from.
 */
class ConsentMode implements OptionSourceInterface
{
    public const OFF = 'off';
    public const MAGENTO = 'magento';
    public const CMP = 'cmp';

    public const MODES = [self::OFF, self::MAGENTO, self::CMP];

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::OFF, 'label' => __('Off')],
            ['value' => self::MAGENTO, 'label' => __('Magento cookie notice')],
            ['value' => self::CMP, 'label' => __('External consent platform (defaults only)')],
        ];
    }
}
