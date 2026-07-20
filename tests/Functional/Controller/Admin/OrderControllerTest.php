<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use App\Utils\Money\DecimalMoney;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

#[Group(name: 'functional')]
class OrderControllerTest extends WebTestCase
{
    public function testListLoads(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/ru/admin/order/list');

        self::assertResponseIsSuccessful();
    }

    public function testEmptyFilterSubmitDoesNotFail(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/order/list');
        $client->submit($crawler->filter('#order_list_filters_block form button[type="submit"]')->form());

        self::assertResponseIsSuccessful();
    }

    public function testTotalPriceRangeFilterDoesNotFail(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/order/list');
        $form = $crawler->filter('#order_list_filters_block form button[type="submit"]')->form([
            'order_filter_form[totalPrice][left_number]' => '10',
            'order_filter_form[totalPrice][right_number]' => '100',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
    }

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

    public function testDateFilterControlsUseDateInputs(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/order/list');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="order_filter_form[createdAt][left_date]"][type="date"]'));
        self::assertCount(1, $crawler->filter('input[name="order_filter_form[createdAt][right_date]"][type="date"]'));
    }

    #[DataProvider(methodName: 'provideDateRanges')]
    public function testDateRangeFilterDoesNotFail(array $submitted): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/order/list');
        $crawler = $client->submit($crawler->filter('#order_list_filters_block form button[type="submit"]')->form($submitted));

        self::assertResponseIsSuccessful();
        self::assertCount(
            0,
            $crawler->filter('#order_list_filters_block .alert.alert-danger[role="alert"]'),
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

        foreach ($expected as $text) {
            self::assertStringContainsString($text, $filters->text());
        }

        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $filters->text());
        }

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
            [
                'Фильтры', 'Значение', 'Применить', 'Сбросить фильтры', 'ID', 'Владелец',
                'Статус', 'Общая стоимость', 'Дата создания', 'От', 'До',
            ],
            ['Filters', 'Show/Hide filters'],
            'Создан',
        ];

        yield 'English' => [
            'en',
            'Filters',
            'Show/Hide filters',
            [
                'Filters', 'Value', 'Apply', 'Reset filters', 'ID', 'Owner', 'Status',
                'Total price', 'Created at', 'From', 'To',
            ],
            ['Фильтры', 'Показать/скрыть фильтры', 'Ошибка диапазона дат'],
            'Created',
        ];
    }

    #[DataProvider(methodName: 'provideListLocales')]
    public function testListUiIsLocalized(
        string $locale,
        string $title,
        string $heading,
        string $addNew,
        string $edit,
        string $status,
        array $headers,
        array $sidebarLabels,
        array $unexpected,
    ): void {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/order/list', $locale));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($title, $crawler->filter('title')->text());
        self::assertSame($heading, trim($crawler->filter('.card.shadow.mb-4 .card-header h6')->text()));
        self::assertSame($addNew, trim($crawler->filter('.card.shadow.mb-4 .card-header a.btn')->text()));
        self::assertSame(
            $headers,
            $crawler->filter('#main_table thead th')->each(static fn (Crawler $header): string => trim($header->text())),
        );
        self::assertSame($edit, trim($crawler->filter('#main_table tbody a.btn-outline-info')->first()->text()));
        self::assertStringContainsString($status, $crawler->filter('#main_table tbody')->text());

        $sidebar = $crawler->filter('#accordionSidebar');
        self::assertCount(1, $sidebar);
        foreach (array_slice($sidebarLabels, 0, -1) as $label) {
            self::assertStringContainsString($label, $sidebar->text());
        }
        self::assertSame($sidebarLabels[0], trim($crawler->filter('.sidebar-brand-text')->text()));
        self::assertSame($sidebarLabels[1], trim($crawler->filter('a[href$="/admin/dashboard"] span')->text()));
        self::assertSame($sidebarLabels[2], trim($crawler->filter('.sidebar-heading')->first()->text()));
        self::assertSame($sidebarLabels[3], trim($crawler->filter('a[data-target="#collapseOrders"] span')->text()));
        self::assertSame($sidebarLabels[4], trim($crawler->filter('a[href$="/admin/order/list"]')->text()));
        self::assertSame($sidebarLabels[5], trim($crawler->filter('a[href$="/admin/order/add"]')->text()));
        self::assertSame($sidebarLabels[6], trim($crawler->filter('.topbar a[target="_blank"]')->text()));

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
            'ru', 'Все заказы', 'Заказы', 'Добавить', 'Редактировать', 'Создан',
            ['ID', 'ФИО', 'Электронная почта', 'Телефон', 'Дата создания', 'Количество товаров', 'Общая стоимость', 'Статус', ''],
            ['Панель администратора', 'Панель управления', 'Продажи', 'Заказы', 'Все записи', 'Добавить', 'Перейти на сайт'],
            ['All orders', 'Orders', 'Add new', 'Edit', 'Go to main site'],
        ];

        yield 'English' => [
            'en', 'All orders', 'Orders', 'Add new', 'Edit', 'Created',
            ['ID', 'Full name', 'Email', 'Phone', 'Created at', 'Product count', 'Total price', 'Status', ''],
            ['Admin panel', 'Dashboard', 'Sales', 'Orders', 'All list', 'Add new', 'Go to main site'],
            ['Все заказы', 'Заказы', 'Добавить', 'Редактировать', 'Перейти на сайт'],
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

    #[DataProvider(methodName: 'provideOrderEditLocales')]
    public function testAddUiIsLocalized(
        string $locale,
        string $pageTitle,
        string $sectionTitle,
        string $addLabel,
        array $labels,
        array $statusChoices,
        string $saveLabel,
        array $unexpected,
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
        self::assertSame(
            $labels,
            $form->filter('label')->each(static fn (Crawler $label): string => trim($label->text())),
        );
        self::assertSame(
            $statusChoices,
            array_values(array_filter(
                $form->filter('select[name="edit_order_form[status]"] option')->each(
                    static fn (Crawler $option): string => trim($option->text()),
                ),
            )),
        );
        $this->assertOwnerOptionIsApplicationData($form);
        self::assertSame($saveLabel, trim($form->filter('button[type="submit"]')->text()));

        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $card->text());
        }
    }

    #[DataProvider(methodName: 'provideOrderEditLocales')]
    public function testEditUiIsLocalized(
        string $locale,
        string $pageTitle,
        string $sectionTitle,
        string $addLabel,
        array $labels,
        array $statusChoices,
        string $saveLabel,
        array $unexpected,
        string $productsHeading,
        string $totalPrice,
        string $deleteRow,
        string $modalTitle,
        string $modalText,
        string $cancel,
        string $close,
    ): void {
        $client = $this->createAdminClient();
        $order = $this->getEditableOrder();
        $crawler = $client->request('GET', sprintf('/%s/admin/order/edit/%d', $locale, $order->getId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($pageTitle, $crawler->filter('title')->text());

        $card = $crawler->filter('.card.shadow.mb-4');
        $form = $card->filter('form[name="edit_order_form"]');
        $modal = $crawler->filter('#approveDeleteModal');
        self::assertCount(1, $card);
        self::assertCount(1, $form);
        self::assertCount(1, $modal);
        self::assertSame($sectionTitle, trim($card->filter('.card-header a.font-weight-bold')->text()));
        self::assertSame($addLabel === 'Добавить' ? 'Редактировать' : 'Edit', trim($card->filter('.card-header h6')->text()));
        self::assertSame($addLabel, trim($card->filter('.card-header a.btn')->text()));
        self::assertStringContainsString('ID:', $card->text());
        self::assertStringContainsString(
            $locale === 'ru' ? 'Дата создания:' : 'Created at:',
            $card->text(),
        );
        self::assertStringContainsString(
            $locale === 'ru' ? 'Дата обновления:' : 'Updated at:',
            $card->text(),
        );
        self::assertSame(
            $labels,
            $form->filter('label')->each(static fn (Crawler $label): string => trim($label->text())),
        );
        self::assertSame(
            $statusChoices,
            array_values(array_filter(
                $form->filter('select[name="edit_order_form[status]"] option')->each(
                    static fn (Crawler $option): string => trim($option->text()),
                ),
            )),
        );
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

    #[DataProvider(methodName: 'provideOrderValidationLocales')]
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
            'ru',
            'Редактирование заказа',
            'Заказы',
            'Добавить',
            ['Владелец', 'Статус', 'Удалён'],
            ['Создан', 'Обработан', 'Укомплектован', 'Доставлен', 'Отклонен'],
            'Сохранить изменения',
            ['Edit order', 'Orders', 'Add new', 'Owner', 'Status', 'Deleted', 'Save changes', 'Products', 'Total price', 'Delete row', 'Are you sure?', 'Order will be deleted.', 'Cancel', 'Close'],
            'Товары',
            'Общая стоимость',
            'Удалить запись',
            'Вы уверены?',
            'Заказ будет удалён.',
            'Отмена',
            'Закрыть',
        ];

        yield 'English' => [
            'en',
            'Edit order',
            'Orders',
            'Add new',
            ['Owner', 'Status', 'Deleted'],
            ['Created', 'Processed', 'Complected', 'Delivered', 'Denied'],
            'Save changes',
            ['Редактирование заказа', 'Заказы', 'Добавить', 'Владелец', 'Статус', 'Удалён', 'Сохранить изменения', 'Товары', 'Общая стоимость', 'Удалить запись', 'Вы уверены?', 'Заказ будет удалён.', 'Отмена', 'Закрыть'],
            'Products',
            'Total price',
            'Delete row',
            'Are you sure?',
            'Order will be deleted.',
            'Cancel',
            'Close',
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
}
