<?php

declare(strict_types=1);

namespace App\Utils\ApiPlatform\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
// >>> see vendor/api-platform/core/src/Core/Bridge/Doctrine/Orm/Extension/QueryItemExtensionInterface.php (14.07.2024)
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface as LegacyQueryNameGeneratorInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
// see vendor/api-platform/core/src/Core/Bridge/Doctrine/Orm/Extension/QueryItemExtensionInterface.php (14.07.2024) <<<
use App\Entity\Product;
use Doctrine\ORM\QueryBuilder;

class FilterProductQueryExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface|LegacyQueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?string $operationName = null
    ): void {
        $this->andWhere($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface|LegacyQueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?string $operationName = null,
        array $context = []
    ): void {
        $this->andWhere($queryBuilder, $resourceClass);
    }

    private function andWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Product::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $queryBuilder->andWhere(
            sprintf("%s.isDeleted='0'", $rootAlias)
        );
    }
}
