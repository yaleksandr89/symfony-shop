<?php

declare(strict_types=1);

namespace App\Utils\ApiPlatform\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Product;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class FilterProductQueryExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->andWhere($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->andWhere($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function andWhere(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
    ): void {
        if (Product::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $deletedParameter = $queryNameGenerator->generateParameterName('product_deleted');
        $queryBuilder
            ->andWhere(sprintf('%s.isDeleted = :%s', $rootAlias, $deletedParameter))
            ->setParameter($deletedParameter, false);

        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $publishedParameter = $queryNameGenerator->generateParameterName('product_published');
            $queryBuilder
                ->andWhere(sprintf('%s.isPublished = :%s', $rootAlias, $publishedParameter))
                ->setParameter($publishedParameter, true);
        }
    }
}
