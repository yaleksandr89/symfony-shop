<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group(name: 'functional')]
class CategoryControllerTest extends WebTestCase
{
    #[DataProvider(methodName: 'provideLocales')]
    #[TestDox('Список, добавление, изменение и валидация категорий работают в выбранной локали')]
    public function testListAddEditPersistenceAndValidationUseSelectedLocale(
        string $locale,
        string $listTitle,
        string $editTitle,
        string $validation,
        string $oppositeValidation,
    ): void {
        $client = $this->createAdminClient();
        $category = $this->getEditableCategory();

        $list = $client->request('GET', sprintf('/%s/admin/category/list', $locale));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($listTitle, $list->filter('title')->text());
        self::assertStringContainsString((string) $category->getTitle(), $list->filter('#main_table')->text());

        $add = $client->request('GET', sprintf('/%s/admin/category/add', $locale));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($editTitle, $add->filter('title')->text());
        self::assertCount(1, $add->filter('form[name="edit_category_form"]'));
        $invalid = $client->submit($add->filter('form[name="edit_category_form"]')->form([
            'edit_category_form[title]' => '',
        ]));
        self::assertStringContainsString($validation, $invalid->filter('form')->text());
        self::assertStringNotContainsString($oppositeValidation, $invalid->filter('form')->text());

        $suffix = str_replace('.', '', uniqid('', true));
        $createdTitle = 'Category '.$locale.' '.$suffix;
        $add = $client->request('GET', sprintf('/%s/admin/category/add', $locale));
        $client->submit($add->filter('form[name="edit_category_form"]')->form([
            'edit_category_form[title]' => $createdTitle,
        ]));
        self::assertResponseRedirects();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $created = self::getContainer()->get(CategoryRepository::class)->findOneBy([
            'title' => ucfirst(strtolower($createdTitle)),
        ]);
        self::assertInstanceOf(Category::class, $created);

        $edit = $client->request('GET', sprintf('/%s/admin/category/edit/%d', $locale, $created->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($editTitle, $edit->filter('title')->text());
        self::assertStringContainsString((string) $created->getTitle(), $edit->filter('.card')->text());

        $updatedTitle = 'Updated '.$locale.' '.$suffix;
        $client->submit($edit->filter('form[name="edit_category_form"]')->form([
            'edit_category_form[title]' => $updatedTitle,
        ]));
        self::assertResponseRedirects();
        $entityManager->clear();
        $updated = self::getContainer()->get(CategoryRepository::class)->find($created->getId());
        self::assertInstanceOf(Category::class, $updated);
        self::assertSame(ucfirst(strtolower($updatedTitle)), $updated->getTitle());
    }

    public static function provideLocales(): Generator
    {
        yield 'Russian' => ['ru', 'Все категории', 'Редактирование категории', 'Укажите название.', 'Title is required.'];
        yield 'English' => ['en', 'All categories', 'Edit category', 'Title is required.', 'Укажите название.'];
    }

    private function getEditableCategory(): Category
    {
        $category = self::getContainer()->get(CategoryRepository::class)->findOneBy(['isDeleted' => false]);
        self::assertInstanceOf(Category::class, $category);

        return $category;
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_ADMIN_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user, 'website');

        return $client;
    }
}
