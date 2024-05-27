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
 * @license https://github.com/sunrise-php/http-header/blob/master/LICENSE
 * @link https://github.com/sunrise-php/http-header
 */

namespace Sunrise\Http\Header;

/**
 * HeaderInterface
 */
interface HeaderInterface
{

    /**
     * Regular Expression for a token validation
     *
     * @link https://tools.ietf.org/html/rfc7230#section-3.2
     *
     * @var string
     */
    public const RFC7230_TOKEN = '/^[\x21\x23-\x27\x2A\x2B\x2D\x2E\x30-\x39\x41-\x5A\x5E-\x7A\x7C\x7E]+$/';

    /**
     * Regular Expression for a field-value validation
     *
     * @link https://tools.ietf.org/html/rfc7230#section-3.2
     *
     * @var string
     */
    public const RFC7230_FIELD_VALUE = '/^[\x09\x20-\x7E\x80-\xFF]*$/';

    /**
     * Regular Expression for a quoted-string validation
     *
     * @link https://tools.ietf.org/html/rfc7230#section-3.2
     *
     * @var string
     */
    public const RFC7230_QUOTED_STRING = '/^[\x09\x20\x21\x23-\x5B\x5D-\x7E\x80-\xFF]*$/';

    /**
     * Gets the header field-name
     *
     * @return string
     */
    public function getFieldName() : string;

    /**
     * Gets the header field-value
     *
     * @return string
     */
    public function getFieldValue() : string;

    /**
     * Converts the header to a string
     *
     * @link http://php.net/manual/en/language.oop5.magic.php#object.tostring
     *
     * @return string
     */
    public function __toString();
}
