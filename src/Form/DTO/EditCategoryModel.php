<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\Category;
use Symfony\Component\Validator\Constraints as Assert;

class EditCategoryModel
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
     * @param Category|null $category
     *
     * @return static
     */
    public static function makeFromCategory(?Category $category): static
    {
        $model = new self();

        if (!$category) {
            return $model;
        }

        $model->id = $category->getId();
        $model->title = $category->getTitle();

        return $model;
    }
}
