<?php

declare(strict_types=1);

namespace App\Catalog\ApiPlatform\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Category;
use Doctrine\ORM\QueryBuilder;

final class FilterCategoryQueryExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Category::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $deletedParameter = $queryNameGenerator->generateParameterName('category_deleted');
        $queryBuilder
            ->andWhere(sprintf('%s.isDeleted = :%s', $rootAlias, $deletedParameter))
            ->setParameter($deletedParameter, false);
    }
}
