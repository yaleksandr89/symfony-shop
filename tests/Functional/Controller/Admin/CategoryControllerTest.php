<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

#[Group(name: 'functional')]
class CategoryControllerTest extends WebTestCase
{
    #[DataProvider(methodName: 'provideLocales')]
    public function testListAddEditAndValidationAreLocalized(
        string $locale,
        array $expected,
        array $unexpected,
    ): void {
        $client = $this->createAdminClient();
        $category = $this->getEditableCategory();

        $list = $client->request('GET', sprintf('/%s/admin/category/list', $locale));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($expected['listTitle'], $list->filter('title')->text());
        self::assertSame($expected['heading'], trim($list->filter('.card-header h6')->text()));
        self::assertSame($expected['headers'], $list->filter('#main_table thead th')->each(
            static fn (Crawler $header): string => trim($header->text()),
        ));
        self::assertSame($expected['edit'], trim($list->filter('#main_table a.btn-outline-info')->first()->text()));
        self::assertStringContainsString((string) $category->getTitle(), $list->filter('#main_table')->text());
        self::assertStringContainsString((string) $category->getSlug(), $list->filter('#main_table')->text());

        $add = $client->request('GET', sprintf('/%s/admin/category/add', $locale));
        self::assertResponseIsSuccessful();
        $this->assertEditUi($add, $expected, false);
        $invalid = $client->submit($add->filter('form[name="edit_category_form"]')->form([
            'edit_category_form[title]' => '',
        ]));
        self::assertStringContainsString($expected['validation'], $invalid->filter('form')->text());
        self::assertStringNotContainsString($unexpected[count($unexpected) - 1], $invalid->filter('form')->text());

        $edit = $client->request('GET', sprintf('/%s/admin/category/edit/%d', $locale, $category->getId()));
        self::assertResponseIsSuccessful();
        $this->assertEditUi($edit, $expected, true);
        self::assertSame($category->getTitle(), trim($edit->filter('.card-header h6')->text()));
        self::assertStringContainsString((string) $category->getSlug(), $edit->filter('.card-body')->text());

        $scoped = $list->filter('.card')->text().' '.$add->filter('.card')->text().' '.$edit->filter('.card, .modal')->text();
        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $scoped);
        }
    }

    private function assertEditUi(Crawler $crawler, array $expected, bool $withModal): void
    {
        self::assertStringContainsString($expected['editTitle'], $crawler->filter('title')->text());
        self::assertSame($expected['heading'], trim($crawler->filter('.card-header a.font-weight-bold')->text()));
        self::assertSame($expected['add'], trim($crawler->filter('.card-header a.btn')->text()));
        self::assertSame($expected['titleField'], trim($crawler->filter('form label')->text()));
        self::assertStringContainsString($expected['slugField'], $crawler->filter('.card-body')->text());
        self::assertSame($expected['save'], trim($crawler->filter('button[type="submit"]')->text()));

        if ($withModal) {
            $modal = $crawler->filter('#approveDeleteModal');
            self::assertSame($expected['delete'], trim($crawler->filter('[data-target="#approveDeleteModal"]')->text()));
            self::assertSame($expected['modalTitle'], trim($modal->filter('.modal-title')->text()));
            self::assertSame($expected['modalText'], trim($modal->filter('.modal-body')->text()));
            self::assertSame($expected['cancel'], trim($modal->filter('.btn-secondary')->text()));
            self::assertSame($expected['close'], $modal->filter('button.close')->attr('aria-label'));
        }
    }

    public static function provideLocales(): Generator
    {
        yield 'Russian' => ['ru', [
            'listTitle' => 'Все категории', 'editTitle' => 'Редактирование категории', 'heading' => 'Категории',
            'headers' => ['ID', 'Название', 'Алиас', ''], 'edit' => 'Редактировать', 'add' => 'Добавить',
            'titleField' => 'Название', 'slugField' => 'Алиас', 'save' => 'Сохранить изменения',
            'validation' => 'Укажите название.', 'delete' => 'Удалить запись', 'modalTitle' => 'Вы уверены?',
            'modalText' => 'Категория будет удалена.', 'cancel' => 'Отмена', 'close' => 'Закрыть',
        ], ['All categories', 'Edit category', 'Categories', 'Add new', 'Save changes', 'Title is required.']];

        yield 'English' => ['en', [
            'listTitle' => 'All categories', 'editTitle' => 'Edit category', 'heading' => 'Categories',
            'headers' => ['ID', 'Title', 'Slug', ''], 'edit' => 'Edit', 'add' => 'Add new',
            'titleField' => 'Title', 'slugField' => 'Slug', 'save' => 'Save changes',
            'validation' => 'Title is required.', 'delete' => 'Delete row', 'modalTitle' => 'Are you sure?',
            'modalText' => 'Category will be deleted.', 'cancel' => 'Cancel', 'close' => 'Close',
        ], ['Все категории', 'Редактирование категории', 'Категории', 'Добавить', 'Сохранить изменения', 'Укажите название.']];
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
