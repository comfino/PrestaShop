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

namespace Comfino\Api\Dto\Order;

use Comfino\Api\Dto\Order\Customer\Address;

class Customer
{
    /** @var string
     * @readonly */
    public $firstName;
    /** @var string
     * @readonly */
    public $lastName;
    /** @var string
     * @readonly */
    public $email;
    /** @var string
     * @readonly */
    public $phoneNumber;
    /** @var string
     * @readonly */
    public $ip;
    /** @var string|null
     * @readonly */
    public $taxId;
    /** @var bool|null
     * @readonly */
    public $regular;
    /** @var bool|null
     * @readonly */
    public $logged;
    /** @var Address|null
     * @readonly */
    public $address;

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $phoneNumber
     * @param string $ip
     * @param string|null $taxId
     * @param bool|null $regular
     * @param bool|null $logged
     * @param Address|null $address
     */
    public function __construct(
        string $firstName,
        string $lastName,
        string $email,
        string $phoneNumber,
        string $ip,
        ?string $taxId,
        ?bool $regular,
        ?bool $logged,
        ?Address $address
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->ip = $ip;
        $this->taxId = $taxId;
        $this->regular = $regular;
        $this->logged = $logged;
        $this->address = $address;
    }
}
