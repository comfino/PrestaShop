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
 * Import classes
 */
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ServerRequestFactory
 *
 * @link https://www.php-fig.org/psr/psr-17/
 */
class ServerRequestFactory implements ServerRequestFactoryInterface
{

    /**
     * Creates a new request from superglobals variables
     *
     * @param array|null $serverParams
     * @param array|null $queryParams
     * @param array|null $cookieParams
     * @param array|null $uploadedFiles
     * @param array|null $parsedBody
     *
     * @return ServerRequestInterface
     *
     * @link http://php.net/manual/en/language.variables.superglobals.php
     * @link https://www.php-fig.org/psr/psr-15/meta/
     */
    public static function fromGlobals(
        ?array $serverParams = null,
        ?array $queryParams = null,
        ?array $cookieParams = null,
        ?array $uploadedFiles = null,
        ?array $parsedBody = null
    ) : ServerRequestInterface {
        $serverParams  = $serverParams  ?? $_SERVER;
        $queryParams   = $queryParams   ?? $_GET;
        $cookieParams  = $cookieParams  ?? $_COOKIE;
        $uploadedFiles = $uploadedFiles ?? $_FILES;
        $parsedBody    = $parsedBody    ?? $_POST;

        return new ServerRequest(
            request_method($serverParams),
            request_uri($serverParams),
            request_headers($serverParams),
            request_body(),
            null, // request target
            request_protocol($serverParams),
            $serverParams,
            $queryParams,
            $cookieParams,
            request_files($uploadedFiles),
            $parsedBody
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []) : ServerRequestInterface
    {
        return new ServerRequest(
            $method,
            $uri,
            request_headers($serverParams),
            null, // body
            null, // request target
            request_protocol($serverParams),
            $serverParams
        );
    }
}
