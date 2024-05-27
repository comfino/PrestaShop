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
 * @license https://github.com/sunrise-php/http-message/blob/master/LICENSE
 * @link https://github.com/sunrise-php/http-message
 */

namespace Sunrise\Http\Message;

/**
 * Import classes
 */
use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Import functions
 */
use function json_encode;
use function json_last_error;
use function json_last_error_msg;

/**
 * Import constants
 */
use const JSON_ERROR_NONE;

/**
 * HTTP Request Message Factory
 *
 * @link https://www.php-fig.org/psr/psr-17/
 */
class RequestFactory implements RequestFactoryInterface
{

    /**
     * {@inheritdoc}
     */
    public function createRequest(string $method, $uri) : RequestInterface
    {
        return new Request($method, $uri);
    }

    /**
     * Creates a JSON request
     *
     * @param string $method
     * @param string|UriInterface|null $uri
     * @param mixed $data
     * @param int $flags
     * @param int $depth
     *
     * @return RequestInterface
     *
     * @throws InvalidArgumentException
     *         If the data cannot be encoded.
     */
    public function createJsonRequest(string $method, $uri, $data, int $flags = 0, int $depth = 512) : RequestInterface
    {
        /**
         * @psalm-suppress UnusedFunctionCall
         */
        json_encode('');

        $json = json_encode($data, $flags, $depth);
        if (JSON_ERROR_NONE <> json_last_error()) {
            throw new InvalidArgumentException(json_last_error_msg());
        }

        $request = new Request($method, $uri, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);

        $request->getBody()->write($json);

        return $request;
    }
}
