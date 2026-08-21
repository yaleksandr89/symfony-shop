<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Commerce\Repository\CartRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[Table(name: '`cart`'),
    Entity(repositoryClass: CartRepository::class)]
#[UniqueConstraint(name: 'uniq_cart_token', columns: ['token'])]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['cart:list']],
            name: 'api_carts_get_collection'
        ),
        new Post(
            normalizationContext: ['groups' => ['cart:list:write']],
            securityPostDenormalize: "is_granted('CART_EDIT', object)",
            exceptionToStatus: [UniqueConstraintViolationException::class => 409],
            name: 'api_carts_post_collection'
        ),
        new Get(
            normalizationContext: ['groups' => ['cart:item']],
            security: "is_granted('CART_READ', object)",
            name: 'api_carts_get_item'
        ),
        new Delete(
            security: "is_granted('CART_DELETE', object)",
            name: 'api_carts_delete_item'
        ),
    ],
    order: ['cartProducts.id' => 'ASC']
)]
class Cart
{
    #[Id, GeneratedValue, Column(type: Types::INTEGER)]
    #[Groups(['cart:list', 'cart:item'])]
    protected ?int $id;

    #[Column(type: Types::STRING, length: 32, nullable: false)]
    #[Groups(['cart:list', 'cart:item', 'cart:list:write'])]
    #[Assert\NotBlank(message: 'Cart token is required.')]
    #[Assert\Length(exactly: 32, exactMessage: 'Cart token must be exactly 32 characters.')]
    #[Assert\Regex(pattern: '/\A[0-9a-f]{32}\z/', message: 'Cart token must contain 32 lowercase hexadecimal characters.')]
    protected ?string $token;

    #[Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $createdAt;

    #[OneToMany(mappedBy: 'cart', targetEntity: CartProduct::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['cart:list', 'cart:item'])]
    protected Collection $cartProducts;

    public function __construct()
    {
        $this->id = null;
        $this->createdAt = new DateTimeImmutable();
        $this->cartProducts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCartProducts(): Collection
    {
        return $this->cartProducts;
    }

    public function addCartProduct(CartProduct $cartProduct): static
    {
        if (!$this->cartProducts->contains($cartProduct)) {
            $this->cartProducts[] = $cartProduct;
            $cartProduct->setCart($this);
        }

        return $this;
    }

    public function removeCartProduct(CartProduct $cartProduct): static
    {
        $this->cartProducts->removeElement($cartProduct);

        return $this;
    }
}
