<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Persistence\ObjectRepository;

final class CategoryManager extends AbstractBaseManager
{
    /**
     * @return ObjectRepository
     */
    public function getRepository(): ObjectRepository
    {
        return $this->em->getRepository(Category::class);
    }

    /**
     * @param object $entity
     */
    public function remove(object $entity): void
    {
        /** @var Category $category */
        $category = $entity;

        /** @var Product[] $linkedProducts */
        $linkedProducts = $category->getProducts()->getValues();

        $this->em->persist($category);

        $category->setIsDeleted(true);
        foreach ($linkedProducts as $linkedProduct) {
            $linkedProduct->setIsDeleted(true);
        }

        $this->em->flush();
    }
}
