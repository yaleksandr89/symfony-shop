<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Category|null find($id, $lockMode = null, $lockVersion = null)
 * @method Category|null findOneBy(array $criteria, array $orderBy = null)
 * @method Category[]    findAll()
 * @method Category[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private ProductImageRepository $productImageRepository,
    ) {
        parent::__construct($registry, Category::class);
    }

    public function findActiveCategory(): ?array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isDeleted = FALSE')
            ->getQuery()
            ->getResult();
    }

    public function forFormQueryBuilderFindActiveCategory(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isDeleted = FALSE');
    }

    public function findActiveCategoryWithJoinProduct(): ?array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isDeleted = FALSE')
            ->join('c.products', 'p')
            ->andWhere('p.isDeleted = FALSE')
            ->andWhere('p.isPublished = TRUE')
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{title: string, slug: string}> */
    public function findActiveNavigationRows(): array
    {
        return $this->createQueryBuilder('category')
            ->select('DISTINCT category.id AS id, category.title AS title, category.slug AS slug')
            ->innerJoin('category.products', 'product')
            ->andWhere('category.isDeleted = false')
            ->andWhere('product.isDeleted = false')
            ->andWhere('product.isPublished = true')
            ->orderBy('category.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return list<array{categoryId: int, categoryTitle: string, categorySlug: string, productId: int, cover: array{filenameBig: string, filenameMiddle: string, filenameSmall: string}}>
     */
    public function findHomepageBannerCandidates(): array
    {
        $rows = $this->createQueryBuilder('category')
            ->select('category.id AS categoryId')
            ->addSelect('category.title AS categoryTitle')
            ->addSelect('category.slug AS categorySlug')
            ->addSelect('product.id AS productId')
            ->innerJoin('category.products', 'product')
            ->andWhere('category.isDeleted = false')
            ->andWhere('product.isDeleted = false')
            ->andWhere('product.isPublished = true')
            ->orderBy('category.id', 'ASC')
            ->addOrderBy('product.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
        $covers = $this->productImageRepository->findFirstCoversByProductIds(array_map(
            static fn (array $row): int => (int) $row['productId'],
            $rows,
        ));

        $candidates = [];
        foreach ($rows as $row) {
            $productId = (int) $row['productId'];
            if (!isset($covers[$productId])) {
                continue;
            }
            $candidates[] = [
                'categoryId' => (int) $row['categoryId'],
                'categoryTitle' => (string) $row['categoryTitle'],
                'categorySlug' => (string) $row['categorySlug'],
                'productId' => $productId,
                'cover' => $covers[$productId],
            ];
        }

        return $candidates;
    }
}
