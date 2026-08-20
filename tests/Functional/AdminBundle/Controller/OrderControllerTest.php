<?php

declare(strict_types=1);

namespace App\Tests\Functional\AdminBundle\Controller;

use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use App\Utils\Money\DecimalMoney;
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

#[Group(name: 'functional')]
class OrderControllerTest extends WebTestCase
{
    #[TestDox('Список заказов загружает счётчики позиций пакетно и не раздувает число запросов')]
    public function testListBatchesLineCountsAndKeepsQueryCountBounded(): void
    {
        $client = $this->createAdminClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $orderRepository = self::getContainer()->get(OrderRepository::class);
        $product = self::getContainer()->get(ProductRepository::class)->findOneBy(['isDeleted' => false]);
        self::assertInstanceOf(Product::class, $product);
        $existingCount = $orderRepository->count(['isDeleted' => false]);
        $orderCount = 10 + ((5 - (($existingCount + 10) % 10) + 10) % 10);
        $suffix = str_replace('.', '', uniqid('', true));
        $owner = (new User())
            ->setEmail('admin-query-owner-'.$suffix.'@example.test')
            ->setPassword('not-used-in-functional-test')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true)
            ->setFullName('Admin Query Owner '.$suffix)
            ->setPhone('+7 900 123-45-67');
        $entityManager->persist($owner);

        $orders = [];
        for ($index = 1; $index <= $orderCount; ++$index) {
            $order = (new Order())
                ->setOwner($owner)
                ->setStatus(0)
                ->setTotalPrice($index === $orderCount ? '0.00' : '39.98');
            if ($index < $orderCount) {
                for ($line = 1; $line <= 2; ++$line) {
                    $order->addOrderProduct(
                        (new OrderProduct())
                            ->setProduct($product)
                            ->setQuantity(1)
                            ->setPricePerOne('19.99')
                    );
                }
            }
            $entityManager->persist($order);
            $orders[] = $order;
        }
        $entityManager->flush();

        $expectedPageIds = array_reverse(array_map(
            static fn (Order $order): int => (int) $order->getId(),
            array_slice($orders, -10),
        ));
        $lastPage = (int) ceil(($existingCount + $orderCount) / 10);
        $entityManager->clear();
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => UserFixtures::USER_ADMIN_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $admin);
        $client->loginUser($admin, 'website');

        $this->resetDoctrineQueryLog();
        $client->enableProfiler();
        $crawler = $client->request('GET', '/en/admin/order/list?sort=o.id&direction=desc');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#main_table tbody tr');
        self::assertCount(10, $rows);
        self::assertSame(
            $expectedPageIds,
            $rows->each(static fn (Crawler $row): int => (int) trim($row->filter('td')->eq(0)->text())),
        );
        self::assertSame((string) $owner->getFullName(), trim($rows->first()->filter('td')->eq(1)->text()));
        self::assertSame((string) $owner->getEmail(), trim($rows->first()->filter('td')->eq(2)->text()));
        self::assertSame((string) $owner->getPhone(), trim($rows->first()->filter('td')->eq(3)->text()));
        self::assertSame('0', trim($rows->first()->filter('td')->eq(5)->text()));
        self::assertSame('2', trim($rows->eq(1)->filter('td')->eq(5)->text()));
        self::assertCount(1, $crawler->filter('#order_list_filters_block form'));
        self::assertGreaterThan(
            0,
            $crawler->filter(sprintf('.navigation a[href*="page=%d"]', $lastPage))->count(),
        );
        $fullPageQueryCount = $this->doctrineQueryCount($client);
        self::assertLessThanOrEqual(9, $fullPageQueryCount);

        $this->resetDoctrineQueryLog();
        $client->enableProfiler();
        $crawler = $client->request('GET', sprintf(
            '/en/admin/order/list?sort=o.id&direction=desc&page=%d',
            $lastPage,
        ));

        self::assertResponseIsSuccessful();
        self::assertCount(5, $crawler->filter('#main_table tbody tr'));
        $partialPageQueryCount = $this->doctrineQueryCount($client);
        self::assertLessThanOrEqual(9, $partialPageQueryCount);
        self::assertLessThanOrEqual(1, abs($fullPageQueryCount - $partialPageQueryCount));
    }

    #[TestDox('Пустая отправка фильтра сохраняет видимый набор заказов')]
    public function testEmptyFilterSubmitKeepsVisibleOrderIds(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/order/list');
        self::assertResponseIsSuccessful();
        $beforeSubmitIds = $this->visibleListIds($crawler);
        self::assertNotSame([], $beforeSubmitIds);

        $crawler = $client->submit($crawler->filter('#order_list_filters_block form button[type="submit"]')->form());

        self::assertResponseIsSuccessful();
        self::assertSame($beforeSubmitIds, $this->visibleListIds($crawler));
    }

    #[DataProvider(methodName: 'provideTotalPriceRanges')]
    #[TestDox('Диапазон итоговой цены отбирает заданные заказы')]
    public function testTotalPriceRangeFiltersControlledOrders(
        array $submitted,
        array $includedPrices,
        array $excludedPrices,
    ): void
    {
        $client = $this->createAdminClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $owner = (new User())
            ->setEmail('admin-filter-owner-'.$suffix.'@example.test')
            ->setPassword('not-used-in-functional-test')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true)
            ->setFullName('Admin Filter Owner '.$suffix)
            ->setPhone('+7 900 123-45-67');
        $entityManager->persist($owner);

        $ordersByPrice = [];
        foreach (['9.99', '10.00', '15.00', '20.00', '20.01'] as $price) {
            $order = (new Order())
                ->setOwner($owner)
                ->setStatus(0)
                ->setTotalPrice($price);
            $entityManager->persist($order);
            $ordersByPrice[$price] = $order;
        }
        $entityManager->flush();

        $crawler = $client->request('GET', '/ru/admin/order/list');
        $crawler = $client->submit($crawler->filter('#order_list_filters_block form button[type="submit"]')->form([
            'order_filter_form[owner]' => (string) $owner->getId(),
            ...$submitted,
        ]));

        self::assertResponseIsSuccessful();
        $visibleIds = $this->visibleListIds($crawler);
        $this->assertVisibleIdsMatchPrices($visibleIds, $ordersByPrice, $includedPrices, $excludedPrices);
    }

    public static function provideTotalPriceRanges(): Generator
    {
        yield 'lower only' => [[
            'order_filter_form[totalPrice][left_number]' => '10.00',
        ], ['10.00', '15.00', '20.00', '20.01'], ['9.99']];
        yield 'upper only' => [[
            'order_filter_form[totalPrice][right_number]' => '20.00',
        ], ['9.99', '10.00', '15.00', '20.00'], ['20.01']];
        yield 'both inclusive' => [[
            'order_filter_form[totalPrice][left_number]' => '10.00',
            'order_filter_form[totalPrice][right_number]' => '20.00',
        ], ['10.00', '15.00', '20.00'], ['9.99', '20.01']];
    }

    #[TestDox('Сортировка по итоговой цене числовая и выводит валюту')]
    public function testTotalPriceSortingIsNumericAndListRendersCurrency(): void
    {
        $client = $this->createAdminClient();
        $ascending = $client->request('GET', '/ru/admin/order/list?sort=o.totalPrice&direction=asc');

        self::assertResponseIsSuccessful();
        $ascendingCents = $this->listTotalPriceCents($ascending);
        self::assertNotSame([], $ascendingCents);
        $expectedAscending = $ascendingCents;
        sort($expectedAscending);
        self::assertSame($expectedAscending, $ascendingCents);

        $descending = $client->request('GET', '/ru/admin/order/list?sort=o.totalPrice&direction=desc');

        self::assertResponseIsSuccessful();
        $descendingCents = $this->listTotalPriceCents($descending);
        $expectedDescending = $descendingCents;
        rsort($expectedDescending);
        self::assertSame($expectedDescending, $descendingCents);
    }

    #[TestDox('Элементы управления фильтром даты используют поля даты')]
    public function testDateFilterControlsUseDateInputs(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/order/list');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="order_filter_form[createdAt][left_date]"][type="date"]'));
        self::assertCount(1, $crawler->filter('input[name="order_filter_form[createdAt][right_date]"][type="date"]'));
    }

    #[DataProvider(methodName: 'provideDateRanges')]
    #[TestDox('Диапазон дат отбирает заданные заказы')]
    public function testDateRangeFiltersControlledOrders(
        array $submitted,
        array $includedDates,
        array $excludedDates,
    ): void
    {
        $client = $this->createAdminClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $owner = (new User())
            ->setEmail('admin-date-filter-owner-'.$suffix.'@example.test')
            ->setPassword('not-used-in-functional-test')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true)
            ->setFullName('Admin Date Filter Owner '.$suffix)
            ->setPhone('+7 900 123-45-68');
        $entityManager->persist($owner);

        $ordersByDate = [];
        foreach ([
            'previous day' => '2026-04-09 23:59:59',
            'selected day early' => '2026-04-10 00:00:00',
            'selected day late' => '2026-04-10 23:59:59',
            'next day midnight' => '2026-04-11 00:00:00',
            'after upper day' => '2026-04-12 00:00:00',
        ] as $label => $createdAt) {
            $order = (new Order())
                ->setOwner($owner)
                ->setStatus(0)
                ->setTotalPrice('10.00')
                ->setCreatedAt(new DateTimeImmutable($createdAt));
            $entityManager->persist($order);
            $ordersByDate[$label] = $order;
        }
        $entityManager->flush();

        $crawler = $client->request('GET', '/ru/admin/order/list');
        $crawler = $client->submit($crawler->filter('#order_list_filters_block form button[type="submit"]')->form([
            'order_filter_form[owner]' => (string) $owner->getId(),
            ...$submitted,
        ]));

        self::assertResponseIsSuccessful();
        $this->assertVisibleIdsMatchDates($this->visibleListIds($crawler), $ordersByDate, $includedDates, $excludedDates);
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
    #[TestDox('Фильтр заказов доступен и показывает ключевые элементы выбранной локали')]
    public function testFilterUiIsLocalized(
        string $locale,
        string $heading,
        string $toggle,
        string $representativeField,
        string $status,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/order/list', $locale));

        self::assertResponseIsSuccessful();
        $filters = $crawler->filter('#order_list_filters_block');
        self::assertCount(1, $filters);
        self::assertStringContainsString(
            $heading,
            $crawler->filterXPath('//*[@id="order_list_filters_btn"]/preceding-sibling::h6')->text(),
        );
        self::assertStringContainsString($toggle, $crawler->filter('#order_list_filters_btn')->text());

        self::assertStringContainsString($representativeField, $filters->text());

        self::assertContains(
            $status,
            $filters->filter('select[name="order_filter_form[status]"] option')->each(
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
            'Общая стоимость',
            'Создан',
        ];

        yield 'English' => [
            'en',
            'Filters',
            'Show/Hide filters',
            'Total price',
            'Created',
        ];
    }

    #[DataProvider(methodName: 'provideListLocales')]
    #[TestDox('Список заказов показывает ключевые действия выбранной локали')]
    public function testListUiIsLocalized(
        string $locale,
        string $title,
        string $heading,
        string $addNew,
        string $edit,
        string $status,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/order/list', $locale));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($title, $crawler->filter('title')->text());
        self::assertSame($heading, trim($crawler->filter('.card.shadow.mb-4 .card-header h6')->text()));
        self::assertSame($addNew, trim($crawler->filter('.card.shadow.mb-4 .card-header a.btn')->text()));
        self::assertSame($edit, trim($crawler->filter('#main_table tbody a.btn-outline-info')->first()->text()));
        self::assertStringContainsString($status, $crawler->filter('#main_table tbody')->text());
    }

    public static function provideListLocales(): Generator
    {
        yield 'Russian' => [
            'ru', 'Все заказы', 'Заказы', 'Добавить', 'Редактировать', 'Создан',
        ];

        yield 'English' => [
            'en', 'All orders', 'Orders', 'Add new', 'Edit', 'Created',
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
        $crawler = $client->request('GET', sprintf('/%s/admin/order/list', $locale));
        $unfilteredRows = $crawler->filter('#main_table tbody tr')->count();
        self::assertGreaterThan(0, $unfilteredRows);

        $crawler = $client->submit($crawler->filter('#order_list_filters_block form button[type="submit"]')->form([
            'order_filter_form[createdAt][left_date]' => '2026-07-15',
            'order_filter_form[createdAt][right_date]' => '2026-07-01',
        ]));

        self::assertResponseIsSuccessful();
        $alert = $crawler->filter('#order_list_filters_block .alert.alert-danger[role="alert"]');
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

    #[DataProvider(methodName: 'provideOrderAddLocales')]
    #[TestDox('Форма добавления заказа доступна в выбранной локали')]
    public function testAddUiIsLocalized(
        string $locale,
        string $pageTitle,
        string $sectionTitle,
        string $addLabel,
        string $saveLabel,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/order/add', $locale));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($pageTitle, $crawler->filter('title')->text());

        $card = $crawler->filter('.card.shadow.mb-4');
        $form = $card->filter('form[name="edit_order_form"]');
        self::assertCount(1, $card);
        self::assertCount(1, $form);
        self::assertSame($sectionTitle, trim($card->filter('.card-header a.font-weight-bold')->text()));
        self::assertSame($addLabel, trim($card->filter('.card-header h6')->text()));
        self::assertSame($addLabel, trim($card->filter('.card-header a.btn')->text()));
        $this->assertOwnerOptionIsApplicationData($form);
        self::assertSame($saveLabel, trim($form->filter('button[type="submit"]')->text()));
    }

    public static function provideOrderAddLocales(): Generator
    {
        yield 'Russian' => ['ru', 'Редактирование заказа', 'Заказы', 'Добавить', 'Сохранить изменения'];
        yield 'English' => ['en', 'Edit order', 'Orders', 'Add new', 'Save changes'];
    }

    #[DataProvider(methodName: 'provideOrderEditLocales')]
    #[TestDox('Форма изменения заказа сохраняет продуктовые сигналы и выбранную локаль')]
    public function testEditUiIsLocalized(
        string $locale,
        string $pageTitle,
        string $sectionTitle,
        string $addLabel,
        string $saveLabel,
        string $productsHeading,
        string $totalPrice,
    ): void {
        $client = $this->createAdminClient();
        $order = $this->getEditableOrder();
        $crawler = $client->request('GET', sprintf('/%s/admin/order/edit/%d', $locale, $order->getId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($pageTitle, $crawler->filter('title')->text());

        $card = $crawler->filter('.card.shadow.mb-4');
        $form = $card->filter('form[name="edit_order_form"]');
        self::assertCount(1, $card);
        self::assertCount(1, $form);
        self::assertSame($sectionTitle, trim($card->filter('.card-header a.font-weight-bold')->text()));
        self::assertSame($addLabel, trim($card->filter('.card-header a.btn')->text()));
        self::assertStringContainsString('ID:', $card->text());
        $this->assertOwnerOptionIsApplicationData($form);
        self::assertSame($saveLabel, trim($form->filter('button[type="submit"]')->text()));
        self::assertStringContainsString($productsHeading, $card->text());
        self::assertStringContainsString($totalPrice, $card->text());
        $totalPriceRow = $card->filterXPath(sprintf(
            './/div[contains(@class, "row")][.//div[contains(normalize-space(), "%s")]]',
            $totalPrice,
        ));
        self::assertCount(1, $totalPriceRow);
        $storedTotal = $order->getTotalPrice();
        self::assertNotNull($storedTotal);
        self::assertSame(
            DecimalMoney::toCents($storedTotal),
            self::currencyTextToCents($totalPriceRow->filter('.col-md-11')->text()),
        );
        self::assertCount(1, $card->filter('#app'));
    }

    #[DataProvider(methodName: 'provideOrderValidationLocales')]
    #[TestDox('Сообщения валидации фильтра локализованы')]
    public function testValidationMessagesAreLocalized(
        string $locale,
        array $messages,
        array $unexpected,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/order/add', $locale));
        $form = $crawler->filter('form[name="edit_order_form"]')->form([
            'edit_order_form[status]' => '',
            'edit_order_form[owner]' => '',
        ]);
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        $invalidForm = $crawler->filter('form[name="edit_order_form"]');
        self::assertCount(1, $invalidForm);
        foreach ($messages as $message) {
            self::assertStringContainsString($message, $invalidForm->text());
        }
        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $invalidForm->text());
        }
    }

    public static function provideOrderEditLocales(): Generator
    {
        yield 'Russian' => [
            'ru', 'Редактирование заказа', 'Заказы', 'Добавить', 'Сохранить изменения', 'Товары', 'Общая стоимость',
        ];

        yield 'English' => [
            'en', 'Edit order', 'Orders', 'Add new', 'Save changes', 'Products', 'Total price',
        ];
    }

    public static function provideOrderValidationLocales(): Generator
    {
        yield 'Russian' => [
            'ru',
            ['Выберите статус.', 'Выберите владельца.'],
            ['Please select a status.', 'Please select an owner.', 'Please select status', 'Please select user'],
        ];

        yield 'English' => [
            'en',
            ['Please select a status.', 'Please select an owner.'],
            ['Выберите статус.', 'Выберите владельца.', 'Please select status', 'Please select user'],
        ];
    }

    #[DataProvider(methodName: 'provideOrderProductTranslationLocales')]
    #[TestDox('Переводы Vue для позиций заказа локализованы')]
    public function testOrderProductVueTranslationsAreLocalized(
        string $locale,
        array $expectedTranslations,
        array $oppositeLanguageTranslations,
    ): void {
        $client = $this->createAdminClient();
        $order = $this->getEditableOrder();
        $crawler = $client->request('GET', sprintf('/%s/admin/order/edit/%d', $locale, $order->getId()));

        self::assertResponseIsSuccessful();
        $staticStoreScript = '';
        foreach ($crawler->filter('script')->each(static fn (Crawler $script): string => $script->text()) as $script) {
            if (str_contains($script, 'window.staticStore.translations')) {
                $staticStoreScript = $script;
                break;
            }
        }

        self::assertNotSame('', $staticStoreScript);
        foreach ([
            'window.staticStore.orderId =',
            'window.staticStore.urlViewProduct =',
            'window.staticStore.urlApiCategory =',
            'window.staticStore.urlApiProduct =',
            'window.staticStore.urlApiOrder =',
            'window.staticStore.urlApiOrderProduct =',
            'window.staticStore.userIsVerified =',
        ] as $assignment) {
            self::assertStringContainsString($assignment, $staticStoreScript);
        }

        self::assertMatchesRegularExpression(
            '/window\\.staticStore\\.translations = (?<translations>\\{.+\\});/u',
            $staticStoreScript,
        );
        preg_match('/window\\.staticStore\\.translations = (?<translations>\\{.+\\});/u', $staticStoreScript, $matches);
        $translations = json_decode($matches['translations'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($expectedTranslations, $translations);
        foreach ($oppositeLanguageTranslations as $translation) {
            self::assertNotContains($translation, $translations);
        }
    }

    public static function provideOrderProductTranslationLocales(): Generator
    {
        yield 'Russian' => [
            'ru',
            [
                'chooseCategory' => 'Выберите категорию',
                'chooseProduct' => 'Выберите товар',
                'quantityPlaceholder' => 'Количество',
                'pricePerItemPlaceholder' => 'Цена за единицу',
                'details' => 'Подробнее',
                'add' => 'Добавить',
                'remove' => 'Удалить',
                'totalPrice' => 'Общая стоимость',
                'insufficientRights' => 'Недостаточно прав. Обратитесь к администратору.',
            ],
            [
                'Choose a category', 'Choose a product', 'Quantity', 'Price per item', 'Details', 'Add', 'Remove',
                'Total price', 'You do not have enough permissions. Contact the administrator.',
            ],
        ];

        yield 'English' => [
            'en',
            [
                'chooseCategory' => 'Choose a category',
                'chooseProduct' => 'Choose a product',
                'quantityPlaceholder' => 'Quantity',
                'pricePerItemPlaceholder' => 'Price per item',
                'details' => 'Details',
                'add' => 'Add',
                'remove' => 'Remove',
                'totalPrice' => 'Total price',
                'insufficientRights' => 'You do not have enough permissions. Contact the administrator.',
            ],
            [
                'Выберите категорию', 'Выберите товар', 'Количество', 'Цена за единицу', 'Подробнее', 'Добавить',
                'Удалить', 'Общая стоимость', 'Недостаточно прав. Обратитесь к администратору.',
            ],
        ];
    }

    private function getEditableOrder(): Order
    {
        $order = self::getContainer()->get(OrderRepository::class)->findOneBy(['isDeleted' => false]);
        self::assertInstanceOf(Order::class, $order);

        return $order;
    }

    /** @return list<int> */
    private function listTotalPriceCents(Crawler $crawler): array
    {
        return $crawler->filter('#main_table tbody tr')->each(
            static fn (Crawler $row): int => self::currencyTextToCents($row->filter('td')->eq(6)->text()),
        );
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
     * @param array<string, Order> $ordersByPrice
     * @param list<string> $includedPrices
     * @param list<string> $excludedPrices
     */
    private function assertVisibleIdsMatchPrices(
        array $visibleIds,
        array $ordersByPrice,
        array $includedPrices,
        array $excludedPrices,
    ): void {
        foreach ($includedPrices as $price) {
            self::assertContains($ordersByPrice[$price]->getId(), $visibleIds, sprintf('Expected %s order to be visible.', $price));
        }

        foreach ($excludedPrices as $price) {
            self::assertNotContains($ordersByPrice[$price]->getId(), $visibleIds, sprintf('Expected %s order to be absent.', $price));
        }
    }

    /**
     * @param list<int> $visibleIds
     * @param array<string, Order> $ordersByDate
     * @param list<string> $includedDates
     * @param list<string> $excludedDates
     */
    private function assertVisibleIdsMatchDates(
        array $visibleIds,
        array $ordersByDate,
        array $includedDates,
        array $excludedDates,
    ): void {
        foreach ($includedDates as $date) {
            self::assertContains($ordersByDate[$date]->getId(), $visibleIds, sprintf('Expected %s order to be visible.', $date));
        }

        foreach ($excludedDates as $date) {
            self::assertNotContains($ordersByDate[$date]->getId(), $visibleIds, sprintf('Expected %s order to be absent.', $date));
        }
    }

    private static function currencyTextToCents(string $currencyText): int
    {
        $currencyText = str_replace(["\u{00A0}", "\u{202F}"], '', $currencyText);
        self::assertMatchesRegularExpression('/(?<amount>\d+[.,]\d{2})/', $currencyText);
        preg_match('/(?<amount>\d+[.,]\d{2})/', $currencyText, $matches);

        return DecimalMoney::toCents(str_replace(',', '.', $matches['amount']));
    }

    private function assertOwnerOptionIsApplicationData(Crawler $form): void
    {
        $option = $form->filter('select[name="edit_order_form[owner]"] option')->eq(1);
        $owner = self::getContainer()->get(UserRepository::class)->find($option->attr('value'));
        self::assertInstanceOf(User::class, $owner);
        self::assertSame(
            preg_replace('/\s+/', ' ', sprintf('#%s / %s / %s', $owner->getId(), $owner->getFullName(), $owner->getEmail())),
            trim($option->text()),
        );
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
