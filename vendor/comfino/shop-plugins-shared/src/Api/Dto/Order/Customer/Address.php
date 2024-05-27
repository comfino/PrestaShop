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

namespace Comfino\Api\Dto\Order\Customer;

class Address
{
    /** @var string|null
     * @readonly */
    public $street;
    /** @var string|null
     * @readonly */
    public $buildingNumber;
    /** @var string|null
     * @readonly */
    public $apartmentNumber;
    /** @var string|null
     * @readonly */
    public $postalCode;
    /** @var string|null
     * @readonly */
    public $city;
    /** @var string|null
     * @readonly */
    public $countryCode;

    /**
     * @param string|null $street
     * @param string|null $buildingNumber
     * @param string|null $apartmentNumber
     * @param string|null $postalCode
     * @param string|null $city
     * @param string|null $countryCode
     */
    public function __construct(
        ?string $street,
        ?string $buildingNumber,
        ?string $apartmentNumber,
        ?string $postalCode,
        ?string $city,
        ?string $countryCode
    ) {
        $this->street = $street;
        $this->buildingNumber = $buildingNumber;
        $this->apartmentNumber = $apartmentNumber;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->countryCode = $countryCode;
    }
}
