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

namespace Comfino;

/** Replacement of enum type to maintain source code compatibility with PHP 7.1 (workaround for Rector transpilation bug). */
abstract class Enum implements \JsonSerializable
{
    /**
     * @readonly
     * @var string
     */
    private $value;

    public function __construct(string $value, bool $strict = true)
    {
        if ($strict && !in_array($value, (new \ReflectionObject($this))->getConstants(), true)) {
            throw new \InvalidArgumentException("Value '$value' does not exist.");
        }

        $this->value = $value;
    }

    public static function values(): array
    {
        return array_values((new \ReflectionClass(static::class))->getConstants());
    }

    public static function names(): array
    {
        return array_keys((new \ReflectionClass(static::class))->getConstants());
    }

    /**
     * @param string $value
     * @param bool $strict
     */
    abstract public static function from($value, $strict = true): self;

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
