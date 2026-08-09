<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

#[Group(name: 'functional')]
class ProductControllerTest extends WebTestCase
{
    public function testListBatchesPageCoversAndKeepsQueryCountBounded(): void
    {
        $client = $this->createAdminClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $productRepository = self::getContainer()->get(ProductRepository::class);
        $existingCount = $productRepository->count(['isDeleted' => false]);
        $productCount = 10 + ((5 - (($existingCount + 10) % 10) + 10) % 10);
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('Admin query category '.$suffix)
            ->setSlug('admin-query-category-'.$suffix);
        $entityManager->persist($category);

        $products = [];
        for ($index = 1; $index <= $productCount; ++$index) {
            $product = (new Product())
                ->setTitle(sprintf('Admin query product %02d %s', $index, $suffix))
                ->setSlug(sprintf('admin-query-product-%02d-%s', $index, $suffix))
                ->setPrice('19.99')
                ->setQuantity($index)
                ->setIsPublished(0 === $index % 2)
                ->setCategory($category);
            if ($index < $productCount) {
                $filename = sprintf('admin-query-%02d-%s.jpg', $index, $suffix);
                $product->addProductImage(
                    (new ProductImage())
                        ->setFilenameBig($filename)
                        ->setFilenameMiddle($filename)
                        ->setFilenameSmall($filename)
                );
            }
            $entityManager->persist($product);
            $products[] = $product;
        }
        $entityManager->flush();

        $expectedPageIds = array_reverse(array_map(
            static fn (Product $product): int => (int) $product->getId(),
            array_slice($products, -10),
        ));
        $coverProduct = $products[$productCount - 2];
        $coverFilename = sprintf('admin-query-%02d-%s.jpg', $productCount - 1, $suffix);
        $categoryTitle = (string) $category->getTitle();
        $lastPage = (int) ceil(($existingCount + $productCount) / 10);
        $entityManager->clear();
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => UserFixtures::USER_ADMIN_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $admin);
        $client->loginUser($admin, 'website');

        $this->resetDoctrineQueryLog();
        $client->enableProfiler();
        $crawler = $client->request('GET', '/en/admin/product/list?sort=p.id&direction=desc');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#main_table tbody tr');
        self::assertCount(10, $rows);
        self::assertSame(
            $expectedPageIds,
            $rows->each(static fn (Crawler $row): int => (int) trim($row->filter('td')->eq(0)->text())),
        );
        self::assertSame($categoryTitle, trim($rows->first()->filter('td')->eq(1)->text()));
        self::assertCount(0, $rows->first()->filter('td')->eq(5)->filter('img'));
        $coverRow = $rows->reduce(
            static fn (Crawler $row): bool => (int) trim($row->filter('td')->eq(0)->text()) === $coverProduct->getId(),
        );
        self::assertCount(1, $coverRow);
        self::assertCount(1, $coverRow->filter(sprintf('img[alt="%s"]', $coverFilename)));
        self::assertStringContainsString(
            sprintf('/uploads/images/products/%d/%s', $coverProduct->getId(), $coverFilename),
            (string) $coverRow->filter('img')->attr('src'),
        );
        self::assertCount(1, $crawler->filter('#product_list_filters_block form'));
        self::assertGreaterThan(
            0,
            $crawler->filter(sprintf('.navigation a[href*="page=%d"]', $lastPage))->count(),
        );
        $fullPageQueryCount = $this->doctrineQueryCount($client);
        self::assertLessThanOrEqual(9, $fullPageQueryCount);

        $this->resetDoctrineQueryLog();
        $client->enableProfiler();
        $crawler = $client->request('GET', sprintf(
            '/en/admin/product/list?sort=p.id&direction=desc&page=%d',
            $lastPage,
        ));

        self::assertResponseIsSuccessful();
        self::assertCount(5, $crawler->filter('#main_table tbody tr'));
        $partialPageQueryCount = $this->doctrineQueryCount($client);
        self::assertLessThanOrEqual(9, $partialPageQueryCount);
        self::assertLessThanOrEqual(1, abs($fullPageQueryCount - $partialPageQueryCount));
    }

    public function testEmptyFilterSubmitDoesNotFail(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/product/list');
        $client->submit($crawler->filter('#product_list_filters_block form button[type="submit"]')->form());

        self::assertResponseIsSuccessful();
    }

    public function testEditPersistsMerchandisingFlags(): void
    {
        $client = $this->createAdminClient();
        $product = $this->getEditableProductWithImage()->setIsNew(false)->setIsOnSale(false);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $crawler = $client->request('GET', sprintf('/en/admin/product/edit/%d', $product->getId()));
        $form = $crawler->filter('form[name="edit_product_form"]')->form([
            'edit_product_form[isNew]' => true,
            'edit_product_form[isOnSale]' => true,
        ]);

        $client->submit($form);

        self::assertResponseRedirects(sprintf('/en/admin/product/edit/%d', $product->getId()));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $updatedProduct = $entityManager->find(Product::class, $product->getId());
        self::assertInstanceOf(Product::class, $updatedProduct);
        self::assertTrue($updatedProduct->getIsNew());
        self::assertTrue($updatedProduct->getIsOnSale());
    }

    public function testPriceRangeFilterDoesNotFail(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/product/list');
        $form = $crawler->filter('#product_list_filters_block form button[type="submit"]')->form([
            'order_filter_form[price][left_number]' => '10',
            'order_filter_form[price][right_number]' => '100',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
    }

    public function testDateFilterControlsUseDateInputs(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/product/list');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="order_filter_form[createdAt][left_date]"][type="date"]'));
        self::assertCount(1, $crawler->filter('input[name="order_filter_form[createdAt][right_date]"][type="date"]'));
    }

    #[DataProvider(methodName: 'provideDateRanges')]
    public function testDateRangeFilterDoesNotFail(array $submitted): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/product/list');
        $crawler = $client->submit($crawler->filter('#product_list_filters_block form button[type="submit"]')->form($submitted));

        self::assertResponseIsSuccessful();
        self::assertCount(
            0,
            $crawler->filter('#product_list_filters_block .alert.alert-danger[role="alert"]'),
        );
    }

    public static function provideDateRanges(): Generator
    {
        yield 'lower only' => [['order_filter_form[createdAt][left_date]' => '2024-01-02']];
        yield 'upper only' => [['order_filter_form[createdAt][right_date]' => '2024-01-03']];
        yield 'both' => [[
            'order_filter_form[createdAt][left_date]' => '2024-01-02',
            'order_filter_form[createdAt][right_date]' => '2024-01-03',
        ]];
    }

    #[DataProvider(methodName: 'provideFilterLocales')]
    public function testFilterUiIsLocalized(
        string $locale,
        string $heading,
        string $toggle,
        array $expected,
        array $unexpected,
        array $booleanOptions,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/product/list', $locale));

        self::assertResponseIsSuccessful();
        $filters = $crawler->filter('#product_list_filters_block');
        self::assertCount(1, $filters);
        self::assertStringContainsString(
            $heading,
            $crawler->filterXPath('//*[@id="product_list_filters_btn"]/preceding-sibling::h6')->text(),
        );
        self::assertStringContainsString($toggle, $crawler->filter('#product_list_filters_btn')->text());

        foreach ($expected as $text) {
            self::assertStringContainsString($text, $filters->text());
        }

        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $filters->text());
        }

        self::assertSame(
            $booleanOptions,
            $filters->filter('select[name="order_filter_form[isPublished]"] option')->each(
                static fn (Crawler $option): string => trim($option->text()),
            ),
        );
    }

    public static function provideFilterLocales(): Generator
    {
        yield 'Russian' => [
            'ru',
            'Фильтры',
            'Показать/скрыть фильтры',
            [
                'Фильтры', 'Значение', 'Применить', 'Сбросить фильтры', 'ID', 'Категория',
                'Заголовок', 'Цена', 'Количество', 'Дата создания', 'От', 'До', 'Опубликован',
            ],
            ['Is Published', 'Show/Hide filters'],
            ['Да или Нет', 'Да', 'Нет'],
        ];

        yield 'English' => [
            'en',
            'Filters',
            'Show/Hide filters',
            [
                'Filters', 'Value', 'Apply', 'Reset filters', 'ID', 'Category', 'Title', 'Price',
                'Quantity', 'Created at', 'From', 'To', 'Published',
            ],
            ['Опубликован', 'Ошибка диапазона дат', 'Дата «От» не может быть позднее даты «До».'],
            ['Yes or No', 'Yes', 'No'],
        ];
    }

    #[DataProvider(methodName: 'provideListLocales')]
    public function testListUiIsLocalized(
        string $locale,
        string $title,
        string $heading,
        string $addNew,
        string $edit,
        array $headers,
        array $sidebarLabels,
        array $unexpected,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/product/list', $locale));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($title, $crawler->filter('title')->text());
        self::assertSame($heading, trim($crawler->filter('.card.shadow.mb-4 .card-header h6')->text()));
        self::assertSame($addNew, trim($crawler->filter('.card.shadow.mb-4 .card-header a.btn')->text()));
        self::assertSame(
            $headers,
            $crawler->filter('#main_table thead th')->each(static fn (Crawler $header): string => trim($header->text())),
        );
        self::assertSame($edit, trim($crawler->filter('#main_table tbody a.btn-outline-info')->first()->text()));

        $sidebar = $crawler->filter('#accordionSidebar');
        self::assertCount(1, $sidebar);
        foreach (array_slice($sidebarLabels, 0, -1) as $label) {
            self::assertStringContainsString($label, $sidebar->text());
        }
        self::assertSame($sidebarLabels[0], trim($crawler->filter('.sidebar-brand-text')->text()));
        self::assertSame($sidebarLabels[1], trim($crawler->filter('a[href$="/admin/dashboard"] span')->text()));
        self::assertSame($sidebarLabels[2], trim($crawler->filter('.sidebar-heading')->first()->text()));
        self::assertSame($sidebarLabels[3], trim($crawler->filter('a[data-target="#collapseOrders"] span')->text()));
        self::assertSame($sidebarLabels[4], trim($crawler->filter('a[data-target="#collapseProducts"] span')->text()));
        self::assertSame($sidebarLabels[5], trim($crawler->filter('a[data-target="#collapseCategories"] span')->text()));
        self::assertSame($sidebarLabels[6], trim($crawler->filter('a[href$="/admin/product/list"]')->text()));
        self::assertSame($sidebarLabels[7], trim($crawler->filter('a[href$="/admin/product/add"]')->text()));
        self::assertSame($sidebarLabels[8], trim($crawler->filter('.topbar a[target="_blank"]')->text()));

        $scopedText = implode(' ', $crawler->filter('#main_table, .card-header, #accordionSidebar, .topbar')->each(
            static fn (Crawler $node): string => $node->text(),
        ));
        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $scopedText);
        }
    }

    public static function provideListLocales(): Generator
    {
        yield 'Russian' => [
            'ru', 'Все товары', 'Товары', 'Добавить', 'Редактировать',
            ['ID', 'Категория', 'Название', 'Цена', 'Количество', 'Обложка', 'Дата создания', 'Опубликован', ''],
            ['Панель администратора', 'Панель управления', 'Продажи', 'Заказы', 'Товары', 'Категории', 'Все записи', 'Добавить', 'Перейти на сайт'],
            ['All products', 'Products', 'Add new', 'Edit', 'Go to main site'],
        ];

        yield 'English' => [
            'en', 'All products', 'Products', 'Add new', 'Edit',
            ['ID', 'Category', 'Title', 'Price', 'Quantity', 'Cover', 'Created at', 'Published', ''],
            ['Admin panel', 'Dashboard', 'Sales', 'Orders', 'Products', 'Categories', 'All list', 'Add new', 'Go to main site'],
            ['Все товары', 'Товары', 'Добавить', 'Редактировать', 'Перейти на сайт'],
        ];
    }

    #[DataProvider(methodName: 'provideReversedDateRangeLocales')]
    public function testReversedDateRangeShowsErrorAndDoesNotApplyFilter(
        string $locale,
        string $title,
        string $message,
    ): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/product/list', $locale));
        $unfilteredRows = $crawler->filter('#main_table tbody tr')->count();
        self::assertGreaterThan(0, $unfilteredRows);

        $crawler = $client->submit($crawler->filter('#product_list_filters_block form button[type="submit"]')->form([
            'order_filter_form[createdAt][left_date]' => '2026-07-15',
            'order_filter_form[createdAt][right_date]' => '2026-07-01',
        ]));

        self::assertResponseIsSuccessful();
        $alert = $crawler->filter('#product_list_filters_block .alert.alert-danger[role="alert"]');
        self::assertCount(1, $alert);
        self::assertStringContainsString($title, $alert->text());
        self::assertStringContainsString(
            $message,
            $alert->text(),
        );
        self::assertSame(
            '2026-07-15',
            $crawler->filter('input[name="order_filter_form[createdAt][left_date]"]')->attr('value'),
        );
        self::assertSame(
            '2026-07-01',
            $crawler->filter('input[name="order_filter_form[createdAt][right_date]"]')->attr('value'),
        );
        self::assertSame($unfilteredRows, $crawler->filter('#main_table tbody tr')->count());
    }

    public static function provideReversedDateRangeLocales(): Generator
    {
        yield 'Russian' => ['ru', 'Ошибка диапазона дат', 'Дата «От» не может быть позднее даты «До».'];
        yield 'English' => ['en', 'Date range error', 'The "From" date cannot be later than the "To" date.'];
    }

    #[DataProvider(methodName: 'provideProductAddLocales')]
    public function testAddUiIsLocalized(
        string $locale,
        string $pageTitle,
        string $sectionTitle,
        string $addLabel,
        string $slugLabel,
        array $labels,
        string $categoryPlaceholder,
        string $saveLabel,
        array $unexpected,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/product/add', $locale));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($pageTitle, $crawler->filter('title')->text());

        $card = $crawler->filter('.card.shadow.mb-4');
        $form = $card->filter('form[name="edit_product_form"]');
        self::assertCount(1, $card);
        self::assertCount(1, $form);
        self::assertSame($sectionTitle, trim($card->filter('.card-header a.font-weight-bold')->text()));
        self::assertSame($addLabel, trim($card->filter('.card-header h6')->text()));
        self::assertSame($addLabel, trim($card->filter('.card-header a.btn')->text()));
        self::assertStringContainsString($slugLabel, $card->text());
        self::assertSame(
            $labels,
            $form->filter('label')->each(static fn (Crawler $label): string => trim($label->text())),
        );
        self::assertSame(
            $categoryPlaceholder,
            trim($form->filter('select[name="edit_product_form[category]"] option')->first()->text()),
        );
        self::assertSame($saveLabel, trim($form->filter('button[type="submit"]')->text()));

        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $card->text());
        }
    }

    public static function provideProductAddLocales(): Generator
    {
        foreach (self::provideProductEditLocales() as $name => $arguments) {
            yield $name => array_slice($arguments, 0, 9);
        }
    }

    #[DataProvider(methodName: 'provideProductEditLocales')]
    public function testEditUiIsLocalized(
        string $locale,
        string $pageTitle,
        string $sectionTitle,
        string $addLabel,
        string $slugLabel,
        array $labels,
        string $categoryPlaceholder,
        string $saveLabel,
        array $unexpected,
        string $currentImages,
        string $imageDelete,
        string $deleteRow,
        string $modalTitle,
        string $modalText,
        string $cancel,
        string $close,
    ): void {
        $client = $this->createAdminClient();
        $product = $this->getEditableProductWithImage();
        $crawler = $client->request('GET', sprintf('/%s/admin/product/edit/%d', $locale, $product->getId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($pageTitle, $crawler->filter('title')->text());

        $card = $crawler->filter('.card.shadow.mb-4');
        $form = $card->filter('form[name="edit_product_form"]');
        $modal = $crawler->filter('#approveDeleteModal');
        self::assertCount(1, $card);
        self::assertCount(1, $form);
        self::assertCount(1, $modal);
        self::assertSame($sectionTitle, trim($card->filter('.card-header a.font-weight-bold')->text()));
        self::assertSame($product->getTitle(), trim($card->filter('.card-header h6')->text()));
        self::assertSame($addLabel, trim($card->filter('.card-header a.btn')->text()));
        self::assertStringContainsString($slugLabel, $card->text());
        self::assertStringContainsString($currentImages, $card->text());
        self::assertSame($imageDelete, trim($card->filter('a.btn-outline-info')->text()));
        self::assertSame(
            $labels,
            $form->filter('label')->each(static fn (Crawler $label): string => trim($label->text())),
        );
        self::assertSame(
            $categoryPlaceholder,
            trim($form->filter('select[name="edit_product_form[category]"] option')->first()->text()),
        );
        self::assertSame($saveLabel, trim($form->filter('button[type="submit"]')->text()));
        self::assertSame($deleteRow, trim($card->filter('[data-target="#approveDeleteModal"]')->text()));
        self::assertSame($modalTitle, trim($modal->filter('.modal-title')->text()));
        self::assertSame($modalText, trim($modal->filter('.modal-body')->text()));
        self::assertSame($cancel, trim($modal->filter('.btn-secondary')->text()));
        self::assertSame($deleteRow, trim($modal->filter('.btn-primary')->text()));
        self::assertSame($close, $modal->filter('button.close')->attr('aria-label'));

        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $card->text().' '.$modal->text());
        }
    }

    #[DataProvider(methodName: 'provideProductValidationLocales')]
    public function testValidationMessagesAreLocalized(
        string $locale,
        array $requiredMessages,
        string $priceMessage,
        array $unexpected,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/product/add', $locale));
        $form = $crawler->filter('form[name="edit_product_form"]')->form([
            'edit_product_form[title]' => '',
            'edit_product_form[price]' => '',
            'edit_product_form[quantity]' => '',
            'edit_product_form[description]' => 'Description',
            'edit_product_form[category]' => '',
        ]);
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        $requiredInvalidForm = $crawler->filter('form[name="edit_product_form"]');
        self::assertCount(1, $requiredInvalidForm);
        foreach ($requiredMessages as $message) {
            self::assertStringContainsString($message, $requiredInvalidForm->text());
        }
        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $requiredInvalidForm->text());
        }

        $crawler = $client->request('GET', sprintf('/%s/admin/product/add', $locale));
        $categoryValue = $crawler->filter('select[name="edit_product_form[category]"] option')->eq(1)->attr('value');
        self::assertNotSame('', $categoryValue);
        $form = $crawler->filter('form[name="edit_product_form"]')->form([
            'edit_product_form[title]' => 'Valid title',
            'edit_product_form[price]' => '0',
            'edit_product_form[quantity]' => '1',
            'edit_product_form[description]' => 'Description',
            'edit_product_form[category]' => $categoryValue,
        ]);
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        $priceInvalidForm = $crawler->filter('form[name="edit_product_form"]');
        self::assertCount(1, $priceInvalidForm);
        self::assertStringContainsString($priceMessage, $priceInvalidForm->text());
        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $priceInvalidForm->text());
        }
    }

    public static function provideProductEditLocales(): Generator
    {
        yield 'Russian' => [
            'ru',
            'Редактирование товара',
            'Товары',
            'Добавить',
            'Алиас',
            ['Заголовок', 'Цена', 'Количество', 'Описание', 'Категория', 'Выберите новое изображение', 'Опубликован', 'Новинка', 'Распродажа', 'Удалён'],
            'Выберите категорию',
            'Сохранить изменения',
            ['Edit Product', 'Products', 'Add new', 'Slug', 'Title', 'Price', 'Quantity', 'Description', 'Category', 'Choose new image', 'Is Published', 'New', 'On sale', 'Is Deleted', 'Save changes', 'Please select a category'],
            'Текущие изображения',
            'Удалить',
            'Удалить запись',
            'Вы уверены?',
            'Товар будет удалён.',
            'Отмена',
            'Закрыть',
        ];

        yield 'English' => [
            'en',
            'Edit product',
            'Products',
            'Add new',
            'Slug',
            ['Title', 'Price', 'Quantity', 'Description', 'Category', 'Choose new image', 'Published', 'New', 'On sale', 'Deleted'],
            'Please select a category',
            'Save changes',
            ['Редактирование товара', 'Товары', 'Добавить', 'Алиас', 'Заголовок', 'Цена', 'Количество', 'Описание', 'Категория', 'Выберите новое изображение', 'Опубликован', 'Новинка', 'Распродажа', 'Удалён', 'Сохранить изменения', 'Выберите категорию'],
            'Current images',
            'Delete',
            'Delete row',
            'Are you sure?',
            'Product will be deleted.',
            'Cancel',
            'Close',
        ];
    }

    public static function provideProductValidationLocales(): Generator
    {
        yield 'Russian' => [
            'ru',
            ['Укажите заголовок.', 'Укажите цену.', 'Укажите количество.', 'Выберите категорию.'],
            'Цена должна быть больше нуля.',
            ['Title is required.', 'Please enter a price.', 'Please indicate a quantity.', 'Please select a category.', 'Price must be greater than zero.'],
        ];

        yield 'English' => [
            'en',
            ['Title is required.', 'Please enter a price.', 'Please indicate a quantity.', 'Please select a category.'],
            'Price must be greater than zero.',
            ['Укажите заголовок.', 'Укажите цену.', 'Укажите количество.', 'Выберите категорию.', 'Цена должна быть больше нуля.'],
        ];
    }

    private function getEditableProductWithImage(): Product
    {
        $product = self::getContainer()->get(ProductRepository::class)->findOneBy(['isDeleted' => false]);
        self::assertInstanceOf(Product::class, $product);

        $image = (new ProductImage())
            ->setFilenameBig('functional-test_big.jpg')
            ->setFilenameMiddle('functional-test_middle.jpg')
            ->setFilenameSmall('functional-test_small.jpg');
        $product->addProductImage($image);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($image);
        $entityManager->flush();

        return $product;
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => UserFixtures::USER_ADMIN_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user, 'website');

        return $client;
    }

    private function doctrineQueryCount(KernelBrowser $client): int
    {
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    private function resetDoctrineQueryLog(): void
    {
        $collector = self::getContainer()->get('data_collector.doctrine');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        $collector->reset();
    }
}
