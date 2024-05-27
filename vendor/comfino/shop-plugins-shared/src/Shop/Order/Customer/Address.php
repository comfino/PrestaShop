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

namespace Comfino\Shop\Order\Customer;

class Address implements AddressInterface
{
    /** @var string|null */
    private $street;
    /** @var string|null */
    private $buildingNumber;
    /** @var string|null */
    private $apartmentNumber;
    /** @var string|null */
    private $postalCode;
    /** @var string|null */
    private $city;
    /** @var string|null */
    private $countryCode;

    /**
     * @param string|null $street
     * @param string|null $buildingNumber
     * @param string|null $apartmentNumber
     * @param string|null $postalCode
     * @param string|null $city
     * @param string|null $countryCode
     */
    public function __construct(
        ?string $street = null,
        ?string $buildingNumber = null,
        ?string $apartmentNumber = null,
        ?string $postalCode = null,
        ?string $city = null,
        ?string $countryCode = null
    ) {
        $this->street = $street;
        $this->buildingNumber = $buildingNumber;
        $this->apartmentNumber = $apartmentNumber;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->countryCode = $countryCode;
    }

    /**
     * @inheritDoc
     */
    public function getStreet(): ?string
    {
        return $this->street;
    }

    /**
     * @inheritDoc
     */
    public function getBuildingNumber(): ?string
    {
        return $this->buildingNumber;
    }

    /**
     * @inheritDoc
     */
    public function getApartmentNumber(): ?string
    {
        return $this->apartmentNumber;
    }

    /**
     * @inheritDoc
     */
    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    /**
     * @inheritDoc
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * @inheritDoc
     */
    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }
}
