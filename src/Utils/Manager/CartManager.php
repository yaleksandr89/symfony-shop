<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Cart;
use Doctrine\Persistence\ObjectRepository;

final class CartManager extends AbstractBaseManager
{
    public function getRepository(): ObjectRepository
    {
        return $this->em->getRepository(Cart::class);
    }
}