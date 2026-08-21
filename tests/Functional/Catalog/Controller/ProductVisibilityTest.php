<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
final class ProductVisibilityTest extends WebTestCase
{
    #[TestDox('Публичная витрина показывает только доступные товары')]
    public function testPublicStorefrontProductVisibility(): void
    {
        $client = self::createClient();
        $published = $this->createProduct(true, false);
        $unpublished = $this->createProduct(false, false);
        $deleted = $this->createProduct(true, true);

        foreach ([(string) $published->getUuid(), $published->getSlug()] as $identifier) {
            $client->request('GET', '/ru/product/'.$identifier);
            self::assertResponseStatusCodeSame(Response::HTTP_OK);
        }

        foreach ([$unpublished, $deleted] as $product) {
            foreach ([(string) $product->getUuid(), $product->getSlug()] as $identifier) {
                $this->assertHiddenProductResponse($client, $product, (string) $identifier);
            }
        }
    }

    #[TestDox('Сессия администратора не даёт просмотреть неопубликованный товар')]
    public function testAdminSessionCannotPreviewUnpublishedProduct(): void
    {
        $client = self::createClient();
        $product = $this->createProduct(false, false);
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');

        foreach ([(string) $product->getUuid(), $product->getSlug()] as $identifier) {
            $this->assertHiddenProductResponse($client, $product, (string) $identifier);
        }
    }

    #[TestDox('Страница товара и похожие карточки показывают бейджи мерчандайзинга')]
    public function testProductDetailAndSimilarCardsRenderMerchandisingBadges(): void
    {
        $client = self::createClient();
        $merchandised = $this->createProduct(true, false)->setIsNew(true)->setIsOnSale(true);
        $ordinary = $this->createProduct(true, false);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/en/product/'.$merchandised->getUuid());

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.product-full .sale-status-new');
        self::assertSelectorCount(1, '.product-full .sale-status-sale');
        self::assertSelectorCount(1, '.product-list .sale-status-new');
        self::assertSelectorCount(1, '.product-list .sale-status-sale');

        $client->request('GET', '/en/product/'.$ordinary->getUuid());

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(0, '.product-full .product-merchandising-statuses');
    }

    private function createProduct(bool $isPublished, bool $isDeleted): Product
    {
        $product = (new Product())
            ->setTitle('Hidden storefront product '.uniqid('', true))
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setDescription('Storefront test product')
            ->setIsPublished($isPublished)
            ->setIsDeleted($isDeleted);
        $category = (new Category())->setTitle('Storefront category '.uniqid('', true));
        $product->setCategory($category);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($category);
        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function assertHiddenProductResponse(AbstractBrowser $client, Product $product, string $identifier): void
    {
        $client->request('GET', '/ru/product/'.$identifier);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertFalse($client->getResponse()->isRedirect());
        self::assertStringNotContainsString($product->getTitle() ?? '', (string) $client->getResponse()->getContent());
    }
}
