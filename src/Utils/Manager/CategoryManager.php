<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityRepository;

final class CategoryManager extends AbstractBaseManager
{
    public function getRepository(): EntityRepository
    {
        return $this->em->getRepository(Category::class);
    }

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
