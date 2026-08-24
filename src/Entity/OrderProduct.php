<?php

declare(strict_types=1);

namespace App\Entity;

use App\Commerce\Repository\OrderProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Symfony\Component\Serializer\Attribute\Groups;

#[Table(name: '`order_product`'),
    Entity(repositoryClass: OrderProductRepository::class)]
class OrderProduct
{
    #[Id, GeneratedValue, Column(type: Types::INTEGER)]
    #[Groups(['order_product:list', 'order:item'])]
    protected ?int $id;

    #[ManyToOne(targetEntity: Order::class, inversedBy: 'orderProducts'), JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Order $appOrder = null;

    #[ManyToOne(targetEntity: Product::class, inversedBy: 'orderProducts'), JoinColumn(nullable: false)]
    #[Groups(['order:item'])]
    protected ?Product $product = null;

    #[Column(type: Types::INTEGER)]
    #[Groups(['order:item'])]
    protected ?int $quantity = null;

    #[Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(['order:item'])]
    protected ?string $pricePerOne = null;

    public function __construct()
    {
        $this->id = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppOrder(): ?Order
    {
        return $this->appOrder;
    }

    public function setAppOrder(?Order $appOrder): static
    {
        $this->appOrder = $appOrder;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getPricePerOne(): ?string
    {
        return $this->pricePerOne;
    }

    public function setPricePerOne(?string $pricePerOne): static
    {
        $this->pricePerOne = $pricePerOne;

        return $this;
    }
}
