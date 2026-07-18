<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\CartProductRepository;
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
    Table(name: '`cart_product`'),
    Entity(repositoryClass: CartProductRepository::class)
]
#[ApiResource(operations: [
    new GetCollection(
        normalizationContext: ['groups' => ['cart_product:list']],
        name: 'api_cart_products_get_collection'
    ),
    new Post(
        normalizationContext: ['groups' => ['cart_product:list:write']],
        securityPostDenormalize: "is_granted('CART_PRODUCT_EDIT', object)",
        name: 'api_cart_products_post_collection'
    ),
    new Get(
        normalizationContext: ['groups' => ['cart_product:item']],
        security: "is_granted('CART_PRODUCT_READ', object)",
        name: 'api_cart_products_get_item'
    ),
    new Delete(
        security: "is_granted('CART_PRODUCT_DELETE', object)",
        name: 'api_cart_products_delete_item'
    ),
    new Patch(
        inputFormats: ['json' => ['application/merge-patch+json']],
        securityPostDenormalize: "is_granted('CART_PRODUCT_EDIT', object)",
        name: 'api_cart_products_patch_item'
    ),
])]
class CartProduct
{
    #[Id, GeneratedValue, Column(type: Types::INTEGER)]
    #[Groups(['cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?int $id;

    #[ManyToOne(targetEntity: Cart::class, inversedBy: 'cartProducts'), JoinColumn(nullable: false)]
    #[Groups(['cart_product:list', 'cart_product:item'])]
    protected ?Cart $cart;

    #[ManyToOne(targetEntity: Product::class, inversedBy: 'cartProducts'), JoinColumn(nullable: false)]
    #[Groups(['cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?Product $product;

    #[Column(type: Types::INTEGER)]
    #[Groups(['cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?int $quantity;

    public function __construct()
    {
        $this->id = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

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
}
