<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Gedmo\Mapping\Annotation\Slug;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

#[
    Table(name: '`product`'),
    Entity(repositoryClass: ProductRepository::class)
]
/**
 * @ApiResource(
 *     collectionOperations={
 *       "get"={
 *          "normalization_context"={"groups"="product:list"}
 *       },
 *       "post"={
 *          "security"="is_granted('ROLE_ADMIN')",
 *          "normalization_context"={"groups"="product:list:write"}
 *       }
 *     },
 *     itemOperations={
 *       "get"={
 *          "normalization_context"={"groups"="product:item"}
 *       },
 *     "patch"={
 *          "security"="is_granted('ROLE_ADMIN')",
 *          "normalization_context"={"groups"="product:item:write"}
 *       }
 *     },
 *     order={
 *          "id"="DESC"
 *     },
 *     attributes={
 *          "pagination_client_items_per_page"=true,
 *          "formats"={"jsonld", "json"}
 *     },
 *     paginationEnabled=true
 * )
 * @ApiFilter(BooleanFilter::class, properties={"isPublished"})
 * @ApiFilter(SearchFilter::class, properties={
 * "category": "exact"
 * })
 */
class Product
{
    /**
     * @ApiProperty(identifier=false)
     */
    #[Id, GeneratedValue, Column(type: Types::INTEGER)]
    #[Groups(['product:list', 'order:item', 'cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?int $id;

    /**
     * @ApiProperty(identifier=true)
     */
    #[Column(type: 'uuid')]
    #[Groups(['product:list', 'product:item', 'order:item', 'cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected UuidV4 $uuid;

    #[Column(type: Types::STRING, length: 255)]
    #[Groups(['product:list', 'product:list:write', 'product:item', 'product:item:write', 'order:item', 'cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?string $title;

    #[Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Groups(['product:list', 'product:list:write', 'product:item', 'product:item:write', 'order:item', 'cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?string $price;

    #[Column(type: Types::INTEGER)]
    #[Groups(['product:list', 'product:list:write', 'product:item', 'product:item:write', 'order:item', 'cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?int $quantity;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    #[Column(type: Types::TEXT, nullable: true)]
    protected ?string $description;

    #[Column(type: Types::BOOLEAN)]
    protected bool $isPublished;

    #[Column(type: Types::BOOLEAN)]
    protected bool $isDeleted;

    #[OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected Collection $productImages;

    #[Slug(fields: ['title'])]
    #[Column(type: Types::STRING, length: 128, unique: true, nullable: true)]
    protected ?string $slug;

    #[ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[Groups(['product:list', 'product:list:write', 'product:item', 'product:item:write', 'order:item', 'cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?Category $category;

    #[OneToMany(mappedBy: 'product', targetEntity: CartProduct::class, orphanRemoval: true)]
    protected Collection $cartProducts;

    #[OneToMany(mappedBy: 'product', targetEntity: OrderProduct::class)]
    protected Collection $orderProducts;

    public function __construct()
    {
        $this->id = null;
        $this->uuid = Uuid::v4();
        $this->isDeleted = false;
        $this->isPublished = false;
        $this->createdAt = new DateTimeImmutable();
        $this->productImages = new ArrayCollection();
        $this->cartProducts = new ArrayCollection();
        $this->orderProducts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): UuidV4
    {
        return $this->uuid;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIsPublished(): ?bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

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

    public function getProductImages(): Collection
    {
        return $this->productImages;
    }

    public function addProductImage(ProductImage $productImage): static
    {
        if (!$this->productImages->contains($productImage)) {
            $this->productImages[] = $productImage;
            $productImage->setProduct($this);
        }

        return $this;
    }

    public function removeProductImage(ProductImage $productImage): static
    {
        // set the owning side to null (unless already changed)
        if ($this->productImages->removeElement($productImage) && $productImage->getProduct() === $this) {
            $productImage->setProduct(null);
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

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
            $cartProduct->setProduct($this);
        }

        return $this;
    }

    public function removeCartProduct(CartProduct $cartProduct): static
    {
        if ($this->cartProducts->removeElement($cartProduct)) {
            // set the owning side to null (unless already changed)
            if ($cartProduct->getProduct() === $this) {
                $cartProduct->setProduct(null);
            }
        }

        return $this;
    }

    public function getOrderProducts(): Collection
    {
        return $this->orderProducts;
    }

    public function addOrderProduct(OrderProduct $orderProduct): static
    {
        if (!$this->orderProducts->contains($orderProduct)) {
            $this->orderProducts[] = $orderProduct;
            $orderProduct->setProduct($this);
        }

        return $this;
    }

    public function removeOrderProduct(OrderProduct $orderProduct): static
    {
        if ($this->orderProducts->removeElement($orderProduct)) {
            // set the owning side to null (unless already changed)
            if ($orderProduct->getProduct() === $this) {
                $orderProduct->setProduct(null);
            }
        }

        return $this;
    }
}
