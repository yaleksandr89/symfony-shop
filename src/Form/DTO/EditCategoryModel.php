<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\Category;

class EditCategoryModel
{
    public function __construct(
        public ?int $id = null,
        public ?string $title = null
    ) {
    }

    public static function makeFromCategory(?Category $category): self
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
