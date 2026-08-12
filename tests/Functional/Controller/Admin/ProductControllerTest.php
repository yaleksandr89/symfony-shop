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
use App\Utils\File\FileSaver;
use App\Utils\FileSystem\FilesystemWorker;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Group(name: 'functional')]
class ProductControllerTest extends WebTestCase
{
    #[TestDox('Список товаров загружает обложки пакетно и не раздувает число запросов')]
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

    #[TestDox('Пустая отправка фильтра сохраняет видимый набор товаров')]
    public function testEmptyFilterSubmitKeepsVisibleProductIds(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/product/list');
        self::assertResponseIsSuccessful();
        $beforeSubmitIds = $this->visibleListIds($crawler);
        self::assertNotSame([], $beforeSubmitIds);

        $crawler = $client->submit($crawler->filter('#product_list_filters_block form button[type="submit"]')->form());

        self::assertResponseIsSuccessful();
        self::assertSame($beforeSubmitIds, $this->visibleListIds($crawler));
    }

    #[TestDox('Редактирование сохраняет флаги мерчандайзинга товара')]
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

    #[TestDox('Ошибка staging выбранного изображения остаётся на форме и не сохраняет изменения товара')]
    public function testUploadStagingFailureRendersSafeErrorWithoutSavingProduct(): void
    {
        $client = $this->createAdminClient();
        $client->disableReboot();
        $product = $this->getEditableProductWithImage();
        $productId = $product->getId();
        self::assertIsInt($productId);
        $originalTitle = $product->getTitle();
        self::assertIsString($originalTitle);
        $filesystem = new Filesystem();
        $blockedTempPath = tempnam(sys_get_temp_dir(), 'blocked-product-upload-');
        self::assertNotFalse($blockedTempPath);
        $uploadPath = sys_get_temp_dir().'/product-upload-'.bin2hex(random_bytes(8)).'.png';
        $image = imagecreatetruecolor(20, 10);
        self::assertInstanceOf(\GdImage::class, $image);
        self::assertTrue(imagepng($image, $uploadPath));

        try {
            self::getContainer()->set(FileSaver::class, new FileSaver(
                self::getContainer()->get(SluggerInterface::class),
                new FilesystemWorker($filesystem),
                $blockedTempPath,
            ));

            $crawler = $client->request('GET', sprintf('/en/admin/product/edit/%d', $productId));
            $form = $crawler->filter('form[name="edit_product_form"]')->form([
                'edit_product_form[title]' => 'This change must not be saved',
            ]);
            $form['edit_product_form[newImage]']->upload($uploadPath);
            $crawler = $client->submit($form);

            self::assertResponseIsSuccessful();
            $renderedForm = $crawler->filter('form[name="edit_product_form"]');
            self::assertCount(1, $renderedForm);
            self::assertStringContainsString(
                'Unable to save the uploaded image. Please try again.',
                $renderedForm->text(),
            );
            self::assertCount(0, $crawler->filter('.alert.alert-success'));

            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();
            $persistedProduct = $entityManager->find(Product::class, $productId);
            self::assertInstanceOf(Product::class, $persistedProduct);
            self::assertSame($originalTitle, $persistedProduct->getTitle());
        } finally {
            $filesystem->remove([$blockedTempPath, $uploadPath]);
        }
    }

    #[DataProvider(methodName: 'providePriceRanges')]
    #[TestDox('Диапазон цены отбирает заданные товары')]
    public function testPriceRangeFiltersControlledProducts(
        array $submitted,
        array $includedPrices,
        array $excludedPrices,
    ): void
    {
        $client = $this->createAdminClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('Admin price filter category '.$suffix)
            ->setSlug('admin-price-filter-category-'.$suffix);
        $entityManager->persist($category);

        $productsByPrice = [];
        foreach (['9.99', '10.00', '15.00', '20.00', '20.01'] as $price) {
            $product = (new Product())
                ->setTitle('Admin price filter product '.$price.' '.$suffix)
                ->setSlug('admin-price-filter-product-'.str_replace('.', '-', $price).'-'.$suffix)
                ->setPrice($price)
                ->setQuantity(1)
                ->setIsPublished(true)
                ->setCategory($category);
            $entityManager->persist($product);
            $productsByPrice[$price] = $product;
        }
        $entityManager->flush();

        $crawler = $client->request('GET', '/ru/admin/product/list');
        $crawler = $client->submit($crawler->filter('#product_list_filters_block form button[type="submit"]')->form([
            'order_filter_form[category]' => (string) $category->getId(),
            ...$submitted,
        ]));

        self::assertResponseIsSuccessful();
        $this->assertVisibleIdsMatchPrices(
            $this->visibleListIds($crawler),
            $productsByPrice,
            $includedPrices,
            $excludedPrices,
        );
    }

    public static function providePriceRanges(): Generator
    {
        yield 'lower only' => [[
            'order_filter_form[price][left_number]' => '10.00',
        ], ['10.00', '15.00', '20.00', '20.01'], ['9.99']];
        yield 'upper only' => [[
            'order_filter_form[price][right_number]' => '20.00',
        ], ['9.99', '10.00', '15.00', '20.00'], ['20.01']];
        yield 'both inclusive' => [[
            'order_filter_form[price][left_number]' => '10.00',
            'order_filter_form[price][right_number]' => '20.00',
        ], ['10.00', '15.00', '20.00'], ['9.99', '20.01']];
    }

    #[TestDox('Элементы управления фильтром даты используют поля даты')]
    public function testDateFilterControlsUseDateInputs(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/product/list');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="order_filter_form[createdAt][left_date]"][type="date"]'));
        self::assertCount(1, $crawler->filter('input[name="order_filter_form[createdAt][right_date]"][type="date"]'));
    }

    #[DataProvider(methodName: 'provideDateRanges')]
    #[TestDox('Диапазон дат отбирает заданные товары')]
    public function testDateRangeFiltersControlledProducts(
        array $submitted,
        array $includedDates,
        array $excludedDates,
    ): void
    {
        $client = $this->createAdminClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('Admin date filter category '.$suffix)
            ->setSlug('admin-date-filter-category-'.$suffix);
        $entityManager->persist($category);

        $productsByDate = [];
        foreach ([
            'previous day' => '2026-04-09 23:59:59',
            'selected day early' => '2026-04-10 00:00:00',
            'selected day late' => '2026-04-10 23:59:59',
            'next day midnight' => '2026-04-11 00:00:00',
            'after upper day' => '2026-04-12 00:00:00',
        ] as $label => $createdAt) {
            $product = (new Product())
                ->setTitle('Admin date filter product '.$label.' '.$suffix)
                ->setSlug('admin-date-filter-product-'.str_replace(' ', '-', $label).'-'.$suffix)
                ->setPrice('10.00')
                ->setQuantity(1)
                ->setIsPublished(true)
                ->setCategory($category)
                ->setCreatedAt(new DateTimeImmutable($createdAt));
            $entityManager->persist($product);
            $productsByDate[$label] = $product;
        }
        $entityManager->flush();

        $crawler = $client->request('GET', '/ru/admin/product/list');
        $crawler = $client->submit($crawler->filter('#product_list_filters_block form button[type="submit"]')->form([
            'order_filter_form[category]' => (string) $category->getId(),
            ...$submitted,
        ]));

        self::assertResponseIsSuccessful();
        $this->assertVisibleIdsMatchDates($this->visibleListIds($crawler), $productsByDate, $includedDates, $excludedDates);
    }

    public static function provideDateRanges(): Generator
    {
        yield 'lower only' => [[
            'order_filter_form[createdAt][left_date]' => '2026-04-10',
        ], ['selected day early', 'selected day late', 'next day midnight', 'after upper day'], ['previous day']];
        yield 'upper only' => [[
            'order_filter_form[createdAt][right_date]' => '2026-04-10',
        ], ['previous day', 'selected day early', 'selected day late'], ['next day midnight', 'after upper day']];
        yield 'both' => [[
            'order_filter_form[createdAt][left_date]' => '2026-04-10',
            'order_filter_form[createdAt][right_date]' => '2026-04-11',
        ], ['selected day early', 'selected day late', 'next day midnight'], ['previous day', 'after upper day']];
        yield 'same day includes the whole calendar day' => [[
            'order_filter_form[createdAt][left_date]' => '2026-04-10',
            'order_filter_form[createdAt][right_date]' => '2026-04-10',
        ], ['selected day early', 'selected day late'], ['previous day', 'next day midnight', 'after upper day']];
    }

    #[DataProvider(methodName: 'provideFilterLocales')]
    #[TestDox('Фильтр товаров доступен и показывает ключевые элементы выбранной локали')]
    public function testFilterUiIsLocalized(
        string $locale,
        string $heading,
        string $toggle,
        string $representativeField,
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

        self::assertStringContainsString($representativeField, $filters->text());

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
            'Опубликован',
            ['Да или Нет', 'Да', 'Нет'],
        ];

        yield 'English' => [
            'en',
            'Filters',
            'Show/Hide filters',
            'Published',
            ['Yes or No', 'Yes', 'No'],
        ];
    }

    #[DataProvider(methodName: 'provideListLocales')]
    #[TestDox('Список товаров показывает ключевые действия выбранной локали')]
    public function testListUiIsLocalized(
        string $locale,
        string $title,
        string $heading,
        string $addNew,
        string $edit,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/product/list', $locale));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($title, $crawler->filter('title')->text());
        self::assertSame($heading, trim($crawler->filter('.card.shadow.mb-4 .card-header h6')->text()));
        self::assertSame($addNew, trim($crawler->filter('.card.shadow.mb-4 .card-header a.btn')->text()));
        self::assertSame($edit, trim($crawler->filter('#main_table tbody a.btn-outline-info')->first()->text()));
    }

    public static function provideListLocales(): Generator
    {
        yield 'Russian' => [
            'ru', 'Все товары', 'Товары', 'Добавить', 'Редактировать',
        ];

        yield 'English' => [
            'en', 'All products', 'Products', 'Add new', 'Edit',
        ];
    }

    #[DataProvider(methodName: 'provideReversedDateRangeLocales')]
    #[TestDox('Обратный диапазон дат показывает ошибку и не применяет фильтр')]
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
    #[TestDox('Форма добавления товара доступна в выбранной локали')]
    public function testAddUiIsLocalized(
        string $locale,
        string $pageTitle,
        string $sectionTitle,
        string $addLabel,
        string $saveLabel,
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
        self::assertSame($saveLabel, trim($form->filter('button[name="edit_product_form[submit]"]')->text()));
    }

    public static function provideProductAddLocales(): Generator
    {
        yield 'Russian' => ['ru', 'Редактирование товара', 'Товары', 'Добавить', 'Сохранить изменения'];
        yield 'English' => ['en', 'Edit product', 'Products', 'Add new', 'Save changes'];
    }

    #[DataProvider(methodName: 'provideProductEditLocales')]
    #[TestDox('Форма изменения товара сохраняет действия с изображениями и выбранную локаль')]
    public function testEditUiIsLocalized(
        string $locale,
        string $pageTitle,
        string $sectionTitle,
        string $addLabel,
        string $saveLabel,
        string $currentImages,
        string $imageDelete,
    ): void {
        $client = $this->createAdminClient();
        $product = $this->getEditableProductWithImage();
        $crawler = $client->request('GET', sprintf('/%s/admin/product/edit/%d', $locale, $product->getId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($pageTitle, $crawler->filter('title')->text());

        $card = $crawler->filter('.card.shadow.mb-4');
        $form = $card->filter('form[name="edit_product_form"]');
        self::assertCount(1, $card);
        self::assertCount(1, $form);
        self::assertSame($sectionTitle, trim($card->filter('.card-header a.font-weight-bold')->text()));
        self::assertSame($product->getTitle(), trim($card->filter('.card-header h6')->text()));
        self::assertSame($addLabel, trim($card->filter('.card-header a.btn')->text()));
        self::assertStringContainsString($currentImages, $card->text());
        $imageDeleteButton = $card->filter('button.btn-outline-info[form^="delete-product-image-"]');
        self::assertCount(1, $imageDeleteButton);
        self::assertSame($imageDelete, trim($imageDeleteButton->text()));
        $externalFormId = (string) $imageDeleteButton->attr('form');
        self::assertCount(1, $card->filter(sprintf('form#%s[action*="/admin/product-image/delete/"]', $externalFormId)));
        self::assertSame($saveLabel, trim($form->filter('button[name="edit_product_form[submit]"]')->text()));
    }

    #[DataProvider(methodName: 'provideProductValidationLocales')]
    #[TestDox('Сообщения валидации фильтра локализованы')]
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
            'ru', 'Редактирование товара', 'Товары', 'Добавить', 'Сохранить изменения', 'Текущие изображения', 'Удалить',
        ];

        yield 'English' => [
            'en', 'Edit product', 'Products', 'Add new', 'Save changes', 'Current images', 'Delete',
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

    /** @return list<int> */
    private function visibleListIds(Crawler $crawler): array
    {
        return $crawler->filter('#main_table tbody tr')->each(
            static fn (Crawler $row): int => (int) trim($row->filter('td')->eq(0)->text()),
        );
    }

    /**
     * @param list<int> $visibleIds
     * @param array<string, Product> $productsByPrice
     * @param list<string> $includedPrices
     * @param list<string> $excludedPrices
     */
    private function assertVisibleIdsMatchPrices(
        array $visibleIds,
        array $productsByPrice,
        array $includedPrices,
        array $excludedPrices,
    ): void {
        foreach ($includedPrices as $price) {
            self::assertContains($productsByPrice[$price]->getId(), $visibleIds, sprintf('Expected %s product to be visible.', $price));
        }

        foreach ($excludedPrices as $price) {
            self::assertNotContains($productsByPrice[$price]->getId(), $visibleIds, sprintf('Expected %s product to be absent.', $price));
        }
    }

    /**
     * @param list<int> $visibleIds
     * @param array<string, Product> $productsByDate
     * @param list<string> $includedDates
     * @param list<string> $excludedDates
     */
    private function assertVisibleIdsMatchDates(
        array $visibleIds,
        array $productsByDate,
        array $includedDates,
        array $excludedDates,
    ): void {
        foreach ($includedDates as $date) {
            self::assertContains($productsByDate[$date]->getId(), $visibleIds, sprintf('Expected %s product to be visible.', $date));
        }

        foreach ($excludedDates as $date) {
            self::assertNotContains($productsByDate[$date]->getId(), $visibleIds, sprintf('Expected %s product to be absent.', $date));
        }
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
