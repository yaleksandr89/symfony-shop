<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\OrderRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Symfony\Component\Serializer\Annotation\Groups;

#[
    Table(name: '`order`'),
    Entity(repositoryClass: OrderRepository::class)
]
/**
 * @ApiResource(
 *     collectionOperations={
 *       "get"={
 *          "normalization_context"={"groups"="order:list"}
 *       },
 *       "post"={
 *          "security"="is_granted('ROLE_USER')",
 *          "normalization_context"={"groups"="order:list:write"}
 *       }
 *     },
 *     itemOperations={
 *       "get"={
 *          "normalization_context"={"groups"="order:item"}
 *       },
 *     },
 * )
 */
class Order
{
    #[Id, GeneratedValue, Column(type: Types::INTEGER)]
    #[Groups(['order:item'])]
    protected ?int $id;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $updatedAt;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'orders'), JoinColumn(nullable: false)]
    protected ?User $owner;

    #[Column(type: Types::INTEGER)]
    #[Groups(['order:item'])]
    protected ?int $status;

    #[Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['order:item'])]
    protected ?float $totalPrice;

    #[Column(type: Types::BOOLEAN)]
    protected bool $isDeleted;

    #[OneToMany(mappedBy: 'appOrder', targetEntity: OrderProduct::class)]
    #[Groups(['order:item'])]
    protected Collection $orderProducts;

    public function __construct()
    {
        $this->id = null;
        $this->isDeleted = false;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->orderProducts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?float $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getIsDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function getOrderProducts(): Collection
    {
        return $this->orderProducts;
    }

    public function addOrderProduct(OrderProduct $orderProduct): self
    {
        if (!$this->orderProducts->contains($orderProduct)) {
            $this->orderProducts[] = $orderProduct;
            $orderProduct->setAppOrder($this);
        }

        return $this;
    }

    public function removeOrderProduct(OrderProduct $orderProduct): self
    {
        if ($this->orderProducts->removeElement($orderProduct)) {
            // set the owning side to null (unless already changed)
            if ($orderProduct->getAppOrder() === $this) {
                $orderProduct->setAppOrder(null);
            }
        }

        return $this;
    }
}
