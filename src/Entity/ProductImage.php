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
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Product::class, inversedBy="productImages")
     * @ORM\JoinColumn(nullable=false)
     */
    private $product;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $filenameBig;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $filenameMiddle;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @Groups({"cart_product:list", "cart_product:item", "cart:list", "cart:item"})
     */
    private $filenameSmall;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        return $this->product;
    }

    /**
     * @param Product|null $product
     *
     * @return $this
     */
    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFilenameBig(): ?string
    {
        return $this->filenameBig;
    }

    /**
     * @param string $filenameBig
     *
     * @return $this
     */
    public function setFilenameBig(string $filenameBig): static
    {
        $this->filenameBig = $filenameBig;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFilenameMiddle(): ?string
    {
        return $this->filenameMiddle;
    }

    /**
     * @param string $filenameMiddle
     *
     * @return $this
     */
    public function setFilenameMiddle(string $filenameMiddle): static
    {
        $this->filenameMiddle = $filenameMiddle;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFilenameSmall(): ?string
    {
        return $this->filenameSmall;
    }

    /**
     * @param string $filenameSmall
     *
     * @return $this
     */
    public function setFilenameSmall(string $filenameSmall): static
    {
        $this->filenameSmall = $filenameSmall;

        return $this;
    }
}
