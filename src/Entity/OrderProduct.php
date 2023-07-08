<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\OrderProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     collectionOperations={
 *       "get"={
 *          "normalization_context"={"groups"="order_product:list"}
 *       },
 *       "post"={
 *          "security"="is_granted('ROLE_ADMIN')",
 *          "normalization_context"={"groups"="order_product:list:write"}
 *       }
 *     },
 *     itemOperations={
 *       "get"={
 *          "normalization_context"={"groups"="order_product:item"}
 *       },
 *       "delete"={
 *          "security"="is_granted('ROLE_ADMIN')",
 *       },
 *     },
 * )
 * @ORM\Entity(repositoryClass=OrderProductRepository::class)
 */
class OrderProduct
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @Groups({"order_product:list", "order:item"})
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity=Order::class, inversedBy="orderProducts", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    protected $appOrder;

    /**
     * @ORM\ManyToOne(targetEntity=Product::class, inversedBy="orderProducts")
     * @ORM\JoinColumn(nullable=false)
     *
     * @Groups({"order:item"})
     */
    protected $product;

    /**
     * @ORM\Column(type="integer")
     *
     * @Groups({"order:item"})
     */
    protected $quantity;

    /**
     * @ORM\Column(type="decimal", precision=15, scale=2)
     *
     * @Groups({"order:item"})
     */
    protected $pricePerOne;

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

    public function getPricePerOne(): float|string|null
    {
        return $this->pricePerOne;
    }

    public function setPricePerOne(float|string $pricePerOne): static
    {
        $this->pricePerOne = $pricePerOne;

        return $this;
    }
}
