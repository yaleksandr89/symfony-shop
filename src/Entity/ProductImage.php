<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductImageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ProductImageRepository::class)
 */
class ProductImage
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\ManyToOne(targetEntity=Product::class, inversedBy="productImages")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Product $product;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $filenameBig;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $filenameMiddle;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $filenameSmall;

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
     * @return $this
     */
    public function setProduct(?Product $product): self
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
     * @return $this
     */
    public function setFilenameBig(string $filenameBig): self
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
     * @return $this
     */
    public function setFilenameMiddle(string $filenameMiddle): self
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
     * @return $this
     */
    public function setFilenameSmall(string $filenameSmall): self
    {
        $this->filenameSmall = $filenameSmall;

        return $this;
    }
}
