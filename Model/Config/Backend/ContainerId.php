<?php
/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace WebRamos\GoogleTagManager\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use WebRamos\GoogleTagManager\Model\Config;

/**
 * Normalises the container id and refuses anything that is not GTM-XXXXXXX.
 */
class ContainerId extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param Config $moduleConfig
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly Config $moduleConfig,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Empty is allowed here; the Enabled field decides whether an id is required.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $id = $this->moduleConfig->normalizeContainerId((string)$this->getValue());
        if ($id !== '' && !preg_match(Config::CONTAINER_ID_PATTERN, $id)) {
            throw new LocalizedException(__(
                '"%1" is not a Google Tag Manager container ID. Use the GTM-XXXXXXX form.',
                $id
            ));
        }
        $this->setValue($id);

        return parent::beforeSave();
    }
}
