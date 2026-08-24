<?php

declare(strict_types=1);

namespace App\Commerce\Repository;

use App\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Cart|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cart|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cart[]    findAll()
 * @method Cart[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findForCheckout(int $cartId): ?Cart
    {
        if ($cartId <= 0) {
            throw new \InvalidArgumentException('Checkout cart ID must be positive.');
        }

        $connection = $this->getEntityManager()->getConnection();
        if (!$connection->isTransactionActive()) {
            throw new \LogicException('Checkout cart must be loaded inside an active transaction.');
        }

        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            return $this->find($cartId, LockMode::PESSIMISTIC_WRITE);
        }

        if ($platform instanceof SQLitePlatform) {
            return $this->find($cartId);
        }

        throw new \LogicException(sprintf('Checkout cart locking is not supported on database platform %s.', $platform::class));
    }
}
