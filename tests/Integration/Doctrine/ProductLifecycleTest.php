<?php

declare(strict_types=1);

namespace App\Tests\Integration\Doctrine;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
final class ProductLifecycleTest extends KernelTestCase
{
    #[TestDox('Doctrine сохраняет merchandising-флаги и обновляет updatedAt при изменении товара')]
    public function testPersistsMerchandisingFlagsAndUpdatesTimestampOnMutation(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $initialTimestamp = new \DateTimeImmutable('2000-01-01 00:00:00');
        $product = (new Product())
            ->setTitle('Lifecycle product '.uniqid('', true))
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setUpdatedAt($initialTimestamp);

        $product->setIsNew(true)->setIsOnSale(true);
        $entityManager->persist($product);
        $entityManager->flush();
        $productId = $product->getId();
        self::assertIsInt($productId);

        $entityManager->clear();
        $persistedProduct = $entityManager->find(Product::class, $productId);
        self::assertInstanceOf(Product::class, $persistedProduct);
        self::assertTrue($persistedProduct->getIsNew());
        self::assertTrue($persistedProduct->getIsOnSale());

        $persistedProduct->setTitle($persistedProduct->getTitle().' updated');
        $entityManager->flush();
        $entityManager->clear();

        $updatedProduct = $entityManager->find(Product::class, $productId);
        self::assertInstanceOf(Product::class, $updatedProduct);
        self::assertGreaterThan($initialTimestamp, $updatedProduct->getUpdatedAt());
        self::assertTrue($updatedProduct->getIsNew());
        self::assertTrue($updatedProduct->getIsOnSale());
    }
}
