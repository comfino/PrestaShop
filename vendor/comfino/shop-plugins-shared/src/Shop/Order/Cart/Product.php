<?php

namespace Comfino\Shop\Order\Cart;

class Product implements ProductInterface
{
    /** @var string */
    private string $name;
    /** @var int */
    private int $price;
    /** @var string|null */
    private ?string $id;
    /** @var string|null */
    private ?string $category;
    /** @var string|null */
    private ?string $ean;
    /** @var string|null */
    private ?string $photoUrl;
    /** @var int[]|null */
    private ?array $categoryIds;

    /**
     * @param string $name
     * @param int $price
     * @param string|null $id
     * @param string|null $category
     * @param string|null $ean
     * @param string|null $photoUrl
     * @param int[]|null $categoryIds
     */
    public function __construct(
        string $name,
        int $price,
        ?string $id = null,
        ?string $category = null,
        ?string $ean = null,
        ?string $photoUrl = null,
        ?array $categoryIds = null
    ) {
        $this->name = $name;
        $this->price = $price;
        $this->id = $id;
        $this->category = $category;
        $this->ean = $ean;
        $this->photoUrl = $photoUrl;
        $this->categoryIds = $categoryIds;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * @inheritDoc
     */
    public function getEan(): ?string
    {
        return $this->ean;
    }

    /**
     * @inheritDoc
     */
    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    /**
     * @inheritDoc
     */
    public function getCategoryIds(): ?array
    {
        return $this->categoryIds;
    }
}
