<?php

namespace Comfino\Api\Dto\Order\Customer;

readonly class Address
{
    /** @var string|null */
    public ?string $street;
    /** @var string|null */
    public ?string $buildingNumber;
    /** @var string|null */
    public ?string $apartmentNumber;
    /** @var string|null */
    public ?string $postalCode;
    /** @var string|null */
    public ?string $city;
    /** @var string|null */
    public ?string $countryCode;

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
