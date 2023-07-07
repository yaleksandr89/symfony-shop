<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\ProductImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=ProductImageRepository::class)
 * @ApiResource(
 *     collectionOperations={
 *       "get"={
 *          "normalization_context"={"groups"="product_image:list"}
 *       },
 *     },
 *     itemOperations={
 *       "get"={
 *          "normalization_context"={"groups"="product_image:item"}
 *       },
 *     },
 * )
 */
class ProductImage
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @Groups({"cart_product:list", "cart_product:item", "cart:list", "cart:item"})
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity=Product::class, inversedBy="productImages")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $product;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected ?string $filenameBig;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $filenameMiddle;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @Groups({"cart_product:list", "cart_product:item", "cart:list", "cart:item"})
     */
    protected $filenameSmall;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFilenameBig(): ?string
    {
        return $this->filenameBig;
    }

    public function setFilenameBig(string $filenameBig): static
    {
        $this->filenameBig = $filenameBig;

        return $this;
    }

    public function getFilenameMiddle(): ?string
    {
        return $this->filenameMiddle;
    }

    public function setFilenameMiddle(string $filenameMiddle): static
    {
        $this->filenameMiddle = $filenameMiddle;

        return $this;
    }

    public function getFilenameSmall(): ?string
    {
        return $this->filenameSmall;
    }

    public function setFilenameSmall(string $filenameSmall): static
    {
        $this->filenameSmall = $filenameSmall;

        return $this;
    }
}
