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
 * Validates where gtm.js is served from: an https URL with a host and an optional path.
 *
 * The host goes into the storefront CSP, and the URL into a script src, so nothing but
 * scheme, host and a plain path is accepted.
 */
class GtmBaseUrl extends Value
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
     * Empty restores the Google default; anything else must be https://host[/path].
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $url = rtrim(trim((string)$this->getValue()), '/');
        if ($url === '') {
            $this->setValue(Config::DEFAULT_GTM_BASE_URL);

            return parent::beforeSave();
        }

        $parts = parse_url($url);
        $valid = is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && $this->hostList->isValid(strtolower($parts['host'] ?? ''))
            && !isset($parts['port'])
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment'])
            && preg_match('#^(/[A-Za-z0-9._~-]+)*$#', $parts['path'] ?? '') === 1;
        if (!$valid) {
            throw new LocalizedException(__(
                'GTM Script Base URL must look like https://host or https://host/path, '
                . 'without port, query or fragment: %1',
                $url
            ));
        }
        $this->setValue($url);

        return parent::beforeSave();
    }
}
