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
 declare(strict_types=1);

/**
 * It's free open-source software released under the MIT License.
 *
 * @author Anatoly Fenric <anatoly@fenric.ru>
 * @copyright Copyright (c) 2018, Anatoly Fenric
 * @license https://github.com/sunrise-php/http-server-request/blob/master/LICENSE
 * @link https://github.com/sunrise-php/http-server-request
 */

namespace Sunrise\Http\ServerRequest;

/**
 * Import functions
 */
use function strncmp;
use function strtolower;
use function strtr;
use function substr;
use function ucwords;

/**
 * Gets the request headers from the given server parameters
 *
 * @param array $server
 *
 * @return array<string, string>
 *
 * @link http://php.net/manual/en/reserved.variables.server.php
 * @link https://datatracker.ietf.org/doc/html/rfc3875#section-4.1.18
 */
function request_headers(array $server) : array
{
    // https://datatracker.ietf.org/doc/html/rfc3875#section-4.1.2
    if (!isset($server['HTTP_CONTENT_LENGTH']) && isset($server['CONTENT_LENGTH'])) {
        $server['HTTP_CONTENT_LENGTH'] = $server['CONTENT_LENGTH'];
    }

    // https://datatracker.ietf.org/doc/html/rfc3875#section-4.1.3
    if (!isset($server['HTTP_CONTENT_TYPE']) && isset($server['CONTENT_TYPE'])) {
        $server['HTTP_CONTENT_TYPE'] = $server['CONTENT_TYPE'];
    }

    $result = [];
    foreach ($server as $key => $value) {
        if (0 <> strncmp('HTTP_', $key, 5)) {
            continue;
        }

        $name = strtr(substr($key, 5), '_', '-');
        $name = ucwords(strtolower($name), '-');

        $result[$name] = $value;
    }

    return $result;
}
