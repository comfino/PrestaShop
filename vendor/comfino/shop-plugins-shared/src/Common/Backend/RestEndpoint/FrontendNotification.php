<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Comfino\Common\Backend\RestEndpoint;

use Comfino\Common\Backend\Cache\StorageAdapterInterface;
use Comfino\Common\Backend\CacheManager;
use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use Comfino\Common\Frontend\FrontendManager;
use Psr\Http\Message\ServerRequestInterface;

class FrontendNotification extends RestEndpoint
{
    /**
     * @readonly
     * @var \Comfino\Common\Backend\CacheManager
     */
    private $cacheManager;
    /**
     * @readonly
     * @var \Comfino\Common\Backend\Cache\StorageAdapterInterface
     */
    private $storageAdapter;
    public function __construct(
        string $name,
        string $endpointUrl,
        CacheManager $cacheManager,
        StorageAdapterInterface $storageAdapter
    ) {
        $this->cacheManager = $cacheManager;
        $this->storageAdapter = $storageAdapter;
        parent::__construct($name, $endpointUrl);

        $this->methods = ['POST', 'PUT', 'PATCH'];
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $serverRequest
     */
    public function processRequest($serverRequest): ?array
    {
        if (!$this->endpointPathMatch($serverRequest)) {
            throw new InvalidEndpoint('Endpoint path does not match request path.');
        }

        if (!is_array($requestPayload = $serverRequest->getParsedBody())) {
            throw new InvalidRequest('Invalid request payload.');
        }

        foreach ($requestPayload as $key => $value) {
            if (in_array($key, FrontendManager::FRONTEND_FRAGMENTS, true)) {
                $this->cacheManager->getCacheBucket(FrontendManager::CACHE_BUCKET_NAME, $this->storageAdapter)->set($key, $value);
            }
        }

        return null;
    }
}
