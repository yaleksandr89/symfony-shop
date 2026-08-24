<?php

declare(strict_types=1);

namespace App\Catalog\Manager;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

final class CategoryManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function remove(Category $category): void
    {
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
