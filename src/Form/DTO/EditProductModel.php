<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\Category;
use App\Entity\Product;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class EditProductModel
{
    /**
     * @var int
     */
    public $id;

    /**
     * @Assert\NotBlank(message="Please enter a title")
     *
     * @var string
     */
    public $title;

    /**
     * @Assert\NotBlank(message="Please enter a price")
     * @Assert\GreaterThanOrEqual(value="0")
     *
     * @var string
     */
    public $price;

    /**
     * @Assert\File(
     *     maxSize = "10M",
     *     mimeTypes = {"image/jpeg","image/png"},
     *     mimeTypesMessage = "Please upload a valid image (*.jpg or *.png)"
     * )
     *
     * @var UploadedFile|null
     */
    public $newImage;

    /**
     * @Assert\NotBlank(message="Please indicate a quantity")
     *
     * @var int
     */
    public $quantity;

    /**
     * @var string
     */
    public $description;

    /**
     * @Assert\NotBlank(message="Please select a category")
     *
     * @var Category
     */
    public $category;

    /**
     * @var bool
     */
    public $isPublished;

    /**
     * @var bool
     */
    public $isDeleted;

    /**
     * @var DateTimeImmutable
     */
    public $createdAt;

    public static function makeFromProduct(Product $product = null): self
    {
        $model = new self();

        if (!$product) {
            return $model;
        }

        $model->id = $product->getId();
        $model->title = $product->getTitle();
        $model->price = $product->getPrice();
        $model->quantity = $product->getQuantity();
        $model->description = $product->getDescription();
        $model->category = $product->getCategory();
        $model->isPublished = $product->getIsPublished();
        $model->isDeleted = $product->getIsDeleted();
        $model->createdAt = $product->getCreatedAt();

        return $model;
    }
}
