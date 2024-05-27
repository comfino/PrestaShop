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
use function sprintf;
use function sscanf;

/**
 * Gets the request protocol version from the given server parameters
 *
 * @param array $server
 *
 * @return string
 *
 * @link http://php.net/manual/en/reserved.variables.server.php
 * @link https://datatracker.ietf.org/doc/html/rfc3875#section-4.1.16
 */
function request_protocol(array $server) : string
{
    if (!isset($server['SERVER_PROTOCOL'])) {
        return '1.1';
    }

    // "HTTP" "/" 1*digit "." 1*digit
    sscanf($server['SERVER_PROTOCOL'], 'HTTP/%d.%d', $major, $minor);

    // e.g.: HTTP/1.1
    if (isset($minor)) {
        return sprintf('%d.%d', $major, $minor);
    }

    // e.g.: HTTP/2
    if (isset($major)) {
        return sprintf('%d', $major);
    }

    return '1.1';
}
