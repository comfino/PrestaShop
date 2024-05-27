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

namespace Comfino\Common\Backend\Factory;

use Comfino\Extended\Api\Client;
use Sunrise\Http\Factory\RequestFactory;
use Sunrise\Http\Factory\ResponseFactory;
use Sunrise\Http\Factory\StreamFactory;

final class ApiClientFactory
{
    public function createClient(
        ?string $apiKey,
        ?string $userAgent,
        ?string $apiHost = null,
        ?string $apiLanguage = null,
        array $curlOptions = []
    ): Client {
        $client = new Client(
            new RequestFactory(),
            new StreamFactory(),
            new \Sunrise\Http\Client\Curl\Client(new ResponseFactory(), $curlOptions),
            $apiKey
        );

        if ($userAgent !== null) {
            $client->setCustomUserAgent($userAgent);
        }

        if ($apiHost !== null) {
            $client->setCustomApiHost($apiHost);
        }

        if ($apiLanguage !== null) {
            $client->setApiLanguage($apiLanguage);
        }

        return $client;
    }
}
