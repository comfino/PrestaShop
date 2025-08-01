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

namespace Comfino\Order;

use Comfino\Order\Customer\AddressInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'CustomerInterface.php';

class Customer implements CustomerInterface
{
    /**
     * @var string
     *
     * @readonly
     */
    private $firstName;

    /**
     * @var string
     *
     * @readonly
     */
    private $lastName;

    /**
     * @var string
     *
     * @readonly
     */
    private $email;

    /**
     * @var string
     *
     * @readonly
     */
    private $phoneNumber;

    /**
     * @var string
     *
     * @readonly
     */
    private $ip;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $taxId;

    /**
     * @var bool|null
     *
     * @readonly
     */
    private $isRegular;

    /**
     * @var bool|null
     *
     * @readonly
     */
    private $isLogged;

    /**
     * @var AddressInterface|null
     *
     * @readonly
     */
    private $address;

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $phoneNumber
     * @param string $ip
     * @param string|null $taxId
     * @param bool|null $isRegular
     * @param bool|null $isLogged
     * @param AddressInterface|null $address
     */
    public function __construct(
        $firstName,
        $lastName,
        $email,
        $phoneNumber,
        $ip,
        $taxId = null,
        $isRegular = null,
        $isLogged = null,
        $address = null
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->ip = $ip;
        $this->taxId = $taxId;
        $this->isRegular = $isRegular;
        $this->isLogged = $isLogged;
        $this->address = $address;
    }

    /**
     * {@inheritDoc}
     */
    public function getFirstName()
    {
        return trim(strip_tags($this->firstName));
    }

    /**
     * {@inheritDoc}
     */
    public function getLastName()
    {
        return trim(strip_tags($this->lastName));
    }

    /**
     * {@inheritDoc}
     */
    public function getEmail()
    {
        return trim(strip_tags($this->email));
    }

    /**
     * {@inheritDoc}
     */
    public function getPhoneNumber()
    {
        return trim(strip_tags($this->phoneNumber));
    }

    /**
     * {@inheritDoc}
     */
    public function getIp()
    {
        return trim($this->ip);
    }

    /**
     * {@inheritDoc}
     */
    public function getTaxId()
    {
        return $this->taxId !== null ? trim(strip_tags($this->taxId)) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function isRegular()
    {
        return $this->isRegular;
    }

    /**
     * {@inheritDoc}
     */
    public function isLogged()
    {
        return $this->isLogged;
    }

    /**
     * {@inheritDoc}
     */
    public function getAddress()
    {
        return $this->address;
    }
}
