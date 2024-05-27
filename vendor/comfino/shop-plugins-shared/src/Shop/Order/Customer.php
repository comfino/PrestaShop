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

namespace Comfino\Shop\Order;

use Comfino\Shop\Order\Customer\AddressInterface;

class Customer implements CustomerInterface
{
    /** @var string */
    private $firstName;
    /** @var string */
    private $lastName;
    /** @var string */
    private $email;
    /** @var string */
    private $phoneNumber;
    /** @var string */
    private $ip;
    /** @var string|null */
    private $taxId;
    /** @var bool|null */
    private $isRegular;
    /** @var bool|null */
    private $isLogged;
    /** @var AddressInterface|null */
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
        string $firstName,
        string $lastName,
        string $email,
        string $phoneNumber,
        string $ip,
        ?string $taxId = null,
        ?bool $isRegular = null,
        ?bool $isLogged = null,
        ?AddressInterface $address = null
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
     * @inheritDoc
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @inheritDoc
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @inheritDoc
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @inheritDoc
     */
    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    /**
     * @inheritDoc
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @inheritDoc
     */
    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    /**
     * @inheritDoc
     */
    public function isRegular(): ?bool
    {
        return $this->isRegular;
    }

    /**
     * @inheritDoc
     */
    public function isLogged(): ?bool
    {
        return $this->isLogged;
    }

    /**
     * @inheritDoc
     */
    public function getAddress(): ?AddressInterface
    {
        return $this->address;
    }
}
