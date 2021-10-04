<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Category;
use App\Form\DTO\EditCategoryModel;
use App\Utils\Manager\CategoryManager;

class CategoryFormHandler
{
    /**
     * @var CategoryManager
     */
    private CategoryManager $categoryManager;

    public function __construct(CategoryManager $categoryManager)
    {
        $this->categoryManager = $categoryManager;
    }

    /**
     * @param EditCategoryModel $editCategoryModel
     * @return Category
     */
    public function processEditForm(EditCategoryModel $editCategoryModel): Category
    {
        $category = new Category();

        if ($editCategoryModel->id) {
            $category = $this->categoryManager->find($editCategoryModel->id);
        }

        $this->categoryManager->persist($category);
        $category = $this->fillingCategoryData($category, $editCategoryModel);
        $this->categoryManager->flush();

        return $category;
    }

    /**
     * @param Category $category
     * @param EditCategoryModel $editCategoryModel
     * @return Category
     */
    private function fillingCategoryData(Category $category, EditCategoryModel $editCategoryModel): Category
    {
        $title = (!is_string($editCategoryModel->title))
            ? (string)$editCategoryModel->title
            : $editCategoryModel->title;

        $category->setTitle($title);

        return $category;
    }
}