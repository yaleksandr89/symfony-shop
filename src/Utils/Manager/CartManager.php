<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Cart;
use Doctrine\ORM\EntityRepository;

final class CartManager extends AbstractBaseManager
{
    public function getRepository(): EntityRepository
    {
        return $this->em->getRepository(Cart::class);
    }
}
