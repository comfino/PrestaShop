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

namespace Comfino\Order\Customer;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'AddressInterface.php';

class Address implements AddressInterface
{
    /**
     * @var string|null
     *
     * @readonly
     */
    private $street;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $buildingNumber;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $apartmentNumber;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $postalCode;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $city;

    /**
     * @var string|null
     *
     * @readonly
     */
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
        $street = null,
        $buildingNumber = null,
        $apartmentNumber = null,
        $postalCode = null,
        $city = null,
        $countryCode = null
    ) {
        $this->street = $street;
        $this->buildingNumber = $buildingNumber;
        $this->apartmentNumber = $apartmentNumber;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->countryCode = $countryCode;
    }

    /**
     * {@inheritDoc}
     */
    public function getStreet()
    {
        return $this->street !== null ? trim(html_entity_decode(strip_tags($this->street))) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getBuildingNumber()
    {
        return $this->buildingNumber ? trim(html_entity_decode(strip_tags($this->buildingNumber))) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getApartmentNumber()
    {
        return $this->apartmentNumber ? trim(html_entity_decode(strip_tags($this->apartmentNumber))) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getPostalCode()
    {
        return $this->postalCode ? trim(html_entity_decode(strip_tags($this->postalCode))) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getCity()
    {
        return $this->city ? trim(html_entity_decode(strip_tags($this->city))) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getCountryCode()
    {
        return $this->countryCode ? trim(html_entity_decode(strip_tags($this->countryCode))) : null;
    }
}
