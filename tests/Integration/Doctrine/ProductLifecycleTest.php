<?php

declare(strict_types=1);

namespace App\Tests\Integration\Doctrine;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
final class ProductLifecycleTest extends KernelTestCase
{
    public function testDefaultsMerchandisingSettersAndUpdatedAtLifecycle(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $initialTimestamp = new \DateTimeImmutable('2000-01-01 00:00:00');
        $product = (new Product())
            ->setTitle('Lifecycle product '.uniqid('', true))
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setUpdatedAt($initialTimestamp);

        self::assertFalse($product->getIsNew());
        self::assertFalse($product->getIsOnSale());
        self::assertInstanceOf(\DateTimeImmutable::class, $product->getCreatedAt());
        self::assertSame($initialTimestamp, $product->getUpdatedAt());

        $product->setIsNew(true)->setIsOnSale(true);
        self::assertTrue($product->getIsNew());
        self::assertTrue($product->getIsOnSale());

        $entityManager->persist($product);
        $entityManager->flush();
        $productId = $product->getId();
        self::assertIsInt($productId);

        $product->setTitle($product->getTitle().' updated');
        $entityManager->flush();
        $entityManager->clear();

        $updatedProduct = $entityManager->find(Product::class, $productId);
        self::assertInstanceOf(Product::class, $updatedProduct);
        self::assertGreaterThan($initialTimestamp, $updatedProduct->getUpdatedAt());
    }
}
