<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductImageRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\DBAL\Types\Types;

#[
    Table(name: '`product_image`'),
    Entity(repositoryClass: ProductImageRepository::class)
]
/**
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
    #[Id, GeneratedValue, Column(type: Types::INTEGER)]
    #[Groups(['cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?int $id;

    #[ManyToOne(targetEntity: Product::class, inversedBy: 'productImages'), JoinColumn(nullable: false)]
    protected ?Product $product;

    #[Column(type: Types::STRING, length: 255)]
    protected ?string $filenameBig;

    #[Column(type: Types::STRING, length: 255)]
    protected ?string $filenameMiddle;

    #[Column(type: Types::STRING, length: 255)]
    #[Groups(['cart_product:list', 'cart_product:item', 'cart:list', 'cart:item'])]
    protected ?string $filenameSmall;

    public function __construct()
    {
        $this->id = null;
    }

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
