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
 * @license https://github.com/sunrise-php/uri/blob/master/LICENSE
 * @link https://github.com/sunrise-php/uri
 */

namespace Sunrise\Uri\Component;

/**
 * Import classes
 */
use Sunrise\Uri\Exception\InvalidUriComponentException;

/**
 * Import functions
 */
use function is_int;

/**
 * URI component "port"
 *
 * @link https://tools.ietf.org/html/rfc3986#section-3.2.3
 */
class Port implements ComponentInterface
{

    /**
     * The component value
     *
     * @var int|null
     */
    protected $value;

    /**
     * Constructor of the class
     *
     * @param mixed $value
     *
     * @throws InvalidUriComponentException
     */
    public function __construct($value)
    {
        $min = 1;
        $max = 2 ** 16;

        if ($value === null) {
            return;
        }

        if (!is_int($value)) {
            throw new InvalidUriComponentException('URI component "port" must be an integer');
        }

        if (!($value >= $min && $value <= $max)) {
            throw new InvalidUriComponentException('Invalid URI component "port"');
        }

        $this->value = $value;
    }

    /**
     * @return int|null
     */
    public function present() : ?int
    {
        return $this->value;
    }
}
