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
use WebRamos\GoogleTagManager\Model\HostList;

/**
 * Refuses to switch the container on without a container id and a production host list.
 *
 * An empty host list means "load nowhere" (the staging guard fails closed), so enabling the
 * module without hosts would look like a broken install; the merchant is told at save time.
 * Values saved in the same request win over stored ones, so the order fields are saved in
 * does not matter.
 */
class Enabled extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param Config $moduleConfig
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
        private readonly Config $moduleConfig,
        private readonly HostList $hostList,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Check the container id and the host list when the value turns the module on.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        if ((string)$this->getValue() !== '1') {
            return parent::beforeSave();
        }

        $containerId = $this->moduleConfig->normalizeContainerId(
            $this->submittedOrStored('container_id', Config::XML_CONTAINER_ID, true)
        );
        if (!preg_match(Config::CONTAINER_ID_PATTERN, $containerId)) {
            throw new LocalizedException(__(
                'Enter a container ID in the GTM-XXXXXXX form before enabling Google Tag Manager.'
            ));
        }

        $hosts = $this->submittedOrStored('allowed_hosts', Config::XML_ALLOWED_HOSTS, false);
        if (!$this->hostList->parse($hosts)) {
            throw new LocalizedException(__(
                'List at least one production host under Allowed Hosts (Default Config scope) before '
                . 'enabling Google Tag Manager. The container loads only on listed hosts.'
            ));
        }

        return parent::beforeSave();
    }

    /**
     * Value of a sibling field from this save request, else the stored one.
     *
     * @param string $field
     * @param string $path
     * @param bool $inScope read the stored value at the scope being saved (else default scope)
     * @return string
     */
    private function submittedOrStored(string $field, string $path, bool $inScope): string
    {
        // Read directly: getFieldsetDataValue() returns null for an inherited field but
        // documents @return string, which static analysis takes at its word.
        $submitted = $this->getData('fieldset_data');
        if (is_array($submitted) && isset($submitted[$field])) {
            return (string)$submitted[$field];
        }

        $scope = (string)$this->getScope();
        if (!$inScope || $scope === '' || $scope === ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return (string)$this->_config->getValue($path);
        }

        return (string)$this->_config->getValue($path, $scope, (int)$this->getScopeId());
    }
}
