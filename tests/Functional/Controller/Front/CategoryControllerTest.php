<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
final class CategoryControllerTest extends WebTestCase
{
    public function testActiveCategoryIsResolvedExplicitlyBySlug(): void
    {
        $client = self::createClient();
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('Explicit category '.$suffix)
            ->setSlug('explicit-category-'.$suffix);
        $product = (new Product())
            ->setTitle('Explicit product '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished(true)
            ->setCategory($category);
        $product->addProductImage(
            (new ProductImage())
                ->setFilenameBig('explicit-'.$suffix.'.jpg')
                ->setFilenameMiddle('explicit-'.$suffix.'.jpg')
                ->setFilenameSmall('explicit-'.$suffix.'.jpg')
        );

        $entityManager = $this->getEntityManager();
        $entityManager->persist($category);
        $entityManager->persist($product);
        $entityManager->flush();

        $url = $this->getRouter()->generate('main_category_show', [
            '_locale' => 'ru',
            'slug' => $category->getSlug(),
        ]);
        self::assertSame('/ru/category/'.$category->getSlug(), $url);
        self::assertStringNotContainsString('/'.$category->getId(), $url);

        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertStringContainsString((string) $category->getTitle(), (string) $client->getResponse()->getContent());
        self::assertStringContainsString((string) $product->getTitle(), (string) $client->getResponse()->getContent());
        self::assertSelectorTextContains('.page-title2', (string) $category->getTitle());
    }

    public function testUnknownSlugRemainsNotFound(): void
    {
        $slug = 'missing-category-'.str_replace('.', '', uniqid('', true));
        $client = self::createClient();
        $client->request('GET', $this->getRouter()->generate('main_category_show', [
            '_locale' => 'ru',
            'slug' => $slug,
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertFalse($client->getResponse()->isRedirect());
    }

    public function testDeletedCategoryKeepsHomepageRedirectAndWarningFlash(): void
    {
        $client = self::createClient();
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('Deleted category '.$suffix)
            ->setSlug('deleted-category-'.$suffix)
            ->setIsDeleted(true);
        $entityManager = $this->getEntityManager();
        $entityManager->persist($category);
        $entityManager->flush();

        $client->request('GET', $this->getRouter()->generate('main_category_show', [
            '_locale' => 'ru',
            'slug' => $category->getSlug(),
        ]));

        self::assertResponseRedirects(
            $this->getRouter()->generate('main_homepage', ['_locale' => 'ru']),
            Response::HTTP_FOUND
        );
        self::assertSame(
            [sprintf('The category %s not found!', $category->getTitle())],
            $client->getRequest()->getSession()->getFlashBag()->peek('warning')
        );
        self::assertStringNotContainsString((string) $category->getTitle(), (string) $client->getResponse()->getContent());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function getRouter(): RouterInterface
    {
        return self::getContainer()->get(RouterInterface::class);
    }
}
