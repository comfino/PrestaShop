<?php

namespace Comfino\Api\Dto\Order;

use Comfino\Api\Dto\Order\Customer\Address;

readonly class Customer
{
    /** @var string */
    public string $firstName;
    /** @var string */
    public string $lastName;
    /** @var string */
    public string $email;
    /** @var string */
    public string $phoneNumber;
    /** @var string */
    public string $ip;
    /** @var string|null */
    public ?string $taxId;
    /** @var bool|null */
    public ?bool $regular;
    /** @var bool|null */
    public ?bool $logged;
    /** @var Address|null */
    public ?Address $address;

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
