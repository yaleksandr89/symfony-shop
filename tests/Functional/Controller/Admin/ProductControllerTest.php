<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

#[Group(name: 'functional')]
class ProductControllerTest extends WebTestCase
{
    public function testListLoads(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/ru/admin/product/list');

        self::assertResponseIsSuccessful();
    }

    public function testEmptyFilterSubmitDoesNotFail(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/product/list');
        $client->submit($crawler->filter('#product_list_filters_block form button[type="submit"]')->form());

        self::assertResponseIsSuccessful();
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
