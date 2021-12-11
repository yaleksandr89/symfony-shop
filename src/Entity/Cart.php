<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\CartRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=CartRepository::class)
 * @ApiResource(
 *     collectionOperations={
 *       "get"={
 *          "normalization_context"={"groups"="cart:list"}
 *       },
 *       "post"={
 *          "normalization_context"={"groups"="cart:list:write"},
 *          "security_post_denormalize"="is_granted('CART_EDIT', object)"
 *       }
 *     },
 *     itemOperations={
 *       "get"={
 *          "normalization_context"={"groups"="cart:item"},
 *          "security"="is_granted('CART_READ', object)"
 *       },
 *       "delete"={
 *          "security"="is_granted('CART_DELETE', object)"
 *       },
 *     },
 *    attributes={
 *          "order"={"cartProducts.id": "ASC"}
 *        }
 *    )
 * )
 */
class Cart
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @Groups({"cart:list", "cart:item"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @Groups({"cart:list", "cart:item", "cart:list:write"})
     */
    private $token;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $createdAt;

    /**
     * @ORM\OneToMany(targetEntity=CartProduct::class, mappedBy="cart", orphanRemoval=true)
     *
     * @Groups({"cart:list", "cart:item"})
     */
    private $cartProducts;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->cartProducts = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @param string|null $token
     *
     * @return $this
     */
    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param DateTimeImmutable $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getCartProducts(): Collection
    {
        return $this->cartProducts;
    }

    /**
     * @param CartProduct $cartProduct
     *
     * @return $this
     */
    public function addCartProduct(CartProduct $cartProduct): self
    {
        if (!$this->cartProducts->contains($cartProduct)) {
            $this->cartProducts[] = $cartProduct;
            $cartProduct->setCart($this);
        }

        return $this;
    }

    /**
     * @param CartProduct $cartProduct
     *
     * @return $this
     */
    public function removeCartProduct(CartProduct $cartProduct): self
    {
        if ($this->cartProducts->removeElement($cartProduct)) {
            // set the owning side to null (unless already changed)
            if ($cartProduct->getCart() === $this) {
                $cartProduct->setCart(null);
            }
        }

        return $this;
    }
}
