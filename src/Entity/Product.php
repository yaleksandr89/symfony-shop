<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
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
        "category": "exact"
 * })
 * @ORM\Entity(repositoryClass=ProductRepository::class)
 */
class Product
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @ApiProperty(identifier=false)
     * @Groups({"product:list", "order:item"})
     */
    private $id;

    /**
     * @ORM\Column(type="uuid")
     *
     * @ApiProperty(identifier=true)
     * @Groups({"product:list", "product:item", "order:item"})
     */
    private $uuid;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @Groups({"product:list", "product:list:write", "product:item", "product:item:write", "order:item"})
     */
    private $title;

    /**
     * @ORM\Column(type="decimal", precision=15, scale=2)
     *
     * @Groups({"product:list", "product:list:write", "product:item", "product:item:write", "order:item"})
     */
    private $price;

    /**
     * @ORM\Column(type="integer")
     *
     * @Groups({"product:list", "product:list:write", "product:item", "product:item:write", "order:item"})
     */
    private $quantity;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isPublished;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isDeleted;

    /**
     * @ORM\OneToMany(targetEntity=ProductImage::class, mappedBy="product", cascade={"persist"}, orphanRemoval=true)
     */
    private $productImages;

    /**
     * @Gedmo\Slug(fields={"title"})
     * @ORM\Column(type="string", length=128, unique=true, nullable=true)
     */
    private $slug;

    /**
     * @ORM\ManyToOne(targetEntity=Category::class, inversedBy="products")
     *
     * @Groups({"product:list", "product:list:write", "product:item", "product:item:write", "order:item"})
     */
    private $category;

    /**
     * @ORM\OneToMany(targetEntity=CartProduct::class, mappedBy="product", orphanRemoval=true)
     */
    private $cartProducts;

    /**
     * @ORM\OneToMany(targetEntity=OrderProduct::class, mappedBy="product")
     */
    private $orderProducts;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
        $this->isDeleted = false;
        $this->isPublished = false;
        $this->createdAt = new DateTimeImmutable();
        $this->productImages = new ArrayCollection();
        $this->cartProducts = new ArrayCollection();
        $this->orderProducts = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return UuidV4
     */
    public function getUuid(): UuidV4
    {
        return $this->uuid;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPrice(): ?string
    {
        return $this->price;
    }

    /**
     * @param string $price
     * @return $this
     */
    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     * @return $this
     */
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
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
     * @return $this
     */
    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function getIsPublished(): ?bool
    {
        return $this->isPublished;
    }

    /**
     * @param bool $isPublished
     * @return $this
     */
    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function getIsDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    /**
     * @param bool $isDeleted
     * @return $this
     */
    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getProductImages(): Collection
    {
        return $this->productImages;
    }

    /**
     * @param ProductImage $productImage
     * @return $this
     */
    public function addProductImage(ProductImage $productImage): self
    {
        if (!$this->productImages->contains($productImage)) {
            $this->productImages[] = $productImage;
            $productImage->setProduct($this);
        }

        return $this;
    }

    /**
     * @param ProductImage $productImage
     * @return $this
     */
    public function removeProductImage(ProductImage $productImage): self
    {
        // set the owning side to null (unless already changed)
        if ($this->productImages->removeElement($productImage) && $productImage->getProduct() === $this) {
            $productImage->setProduct(null);
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * @param string|null $slug
     * @return $this
     */
    public function setSlug(?string $slug): self
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

    /**
     * @return Collection
     */
    public function getCartProducts(): Collection
    {
        return $this->cartProducts;
    }

    /**
     * @param CartProduct $cartProduct
     * @return $this
     */
    public function addCartProduct(CartProduct $cartProduct): self
    {
        if (!$this->cartProducts->contains($cartProduct)) {
            $this->cartProducts[] = $cartProduct;
            $cartProduct->setProduct($this);
        }

        return $this;
    }

    /**
     * @param CartProduct $cartProduct
     * @return $this
     */
    public function removeCartProduct(CartProduct $cartProduct): self
    {
        if ($this->cartProducts->removeElement($cartProduct)) {
            // set the owning side to null (unless already changed)
            if ($cartProduct->getProduct() === $this) {
                $cartProduct->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getOrderProducts(): Collection
    {
        return $this->orderProducts;
    }

    /**
     * @param OrderProduct $orderProduct
     * @return $this
     */
    public function addOrderProduct(OrderProduct $orderProduct): self
    {
        if (!$this->orderProducts->contains($orderProduct)) {
            $this->orderProducts[] = $orderProduct;
            $orderProduct->setProduct($this);
        }

        return $this;
    }

    /**
     * @param OrderProduct $orderProduct
     * @return $this
     */
    public function removeOrderProduct(OrderProduct $orderProduct): self
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
