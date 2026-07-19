<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\OrderProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Symfony\Component\Serializer\Annotation\Groups;

#[
    Table(name: '`order_product`'),
    Entity(repositoryClass: OrderProductRepository::class)
]
#[ApiResource(operations: [
    new GetCollection(
        normalizationContext: ['groups' => ['order_product:list']],
        name: 'api_order_products_get_collection'
    ),
    new Post(
        normalizationContext: ['groups' => ['order_product:list:write']],
        security: "is_granted('ROLE_ADMIN')",
        name: 'api_order_products_post_collection'
    ),
    new Get(
        normalizationContext: ['groups' => ['order_product:item']],
        name: 'api_order_products_get_item'
    ),
    new Delete(
        security: "is_granted('ROLE_ADMIN')",
        name: 'api_order_products_delete_item'
    ),
])]
class OrderProduct
{
    #[Id, GeneratedValue, Column(type: Types::INTEGER)]
    #[Groups(['order_product:list', 'order:item'])]
    protected ?int $id;

    #[ManyToOne(targetEntity: Order::class, inversedBy: 'orderProducts'), JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['order:item'])]
    protected ?Order $appOrder;

    #[ManyToOne(targetEntity: Product::class, inversedBy: 'orderProducts'), JoinColumn(nullable: false)]
    #[Groups(['order:item'])]
    protected ?Product $product;

    #[Column(type: Types::INTEGER)]
    #[Groups(['order:item'])]
    protected ?int $quantity;

    #[Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(['order:item'])]
    protected ?string $pricePerOne;

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
