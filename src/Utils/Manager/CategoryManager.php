<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Category;
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
}