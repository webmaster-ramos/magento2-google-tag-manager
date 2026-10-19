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
use WebRamos\GoogleTagManager\Model\HostList;

/**
 * Validates the extra CSP hosts so the field cannot be used to switch the checkout CSP off.
 *
 * A bare "*", a scheme ("https:"), a keyword ("'unsafe-inline'"), "data:" or a path would
 * widen the policy far beyond one host; only hostnames with an optional "*." pass.
 */
class CspHosts extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param HostList $hostList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly HostList $hostList,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Refuse anything but hostnames; normalise to one lower-case host per line.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $hosts = $this->hostList->parse((string)$this->getValue());
        $invalid = $this->hostList->invalid($hosts, true);
        if ($invalid) {
            throw new LocalizedException(__(
                'Additional Tag Hosts accepts hostnames only, optionally starting with "*.": %1',
                implode(', ', $invalid)
            ));
        }
        $this->setValue(implode("\n", $hosts));

        return parent::beforeSave();
    }
}
