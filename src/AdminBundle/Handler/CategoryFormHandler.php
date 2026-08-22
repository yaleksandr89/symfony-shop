<?php

declare(strict_types=1);

namespace App\AdminBundle\Handler;

use App\AdminBundle\DTO\EditCategoryModel;
use App\Catalog\Repository\CategoryRepository;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;

class CategoryFormHandler
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function processEditForm(EditCategoryModel $editCategoryModel): Category
    {
        $category = new Category();

        if ($editCategoryModel->id) {
            $category = $this->categoryRepository->find($editCategoryModel->id);
        }

        $this->entityManager->persist($category);
        $category = $this->fillingCategoryData($category, $editCategoryModel);
        $this->entityManager->flush();

        return $category;
    }

    private function fillingCategoryData(Category $category, EditCategoryModel $editCategoryModel): Category
    {
        $category->setTitle($editCategoryModel->title);

        return $category;
    }
}
