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
class UserControllerTest extends WebTestCase
{
    #[DataProvider(methodName: 'provideLocales')]
    public function testListAddEditAndDuplicateValidationAreLocalized(
        string $locale,
        array $expected,
        array $unexpected,
    ): void {
        $client = $this->createSuperAdminClient();
        $editableUser = $this->getUser(UserFixtures::USER_1_EMAIL);

        $list = $client->request('GET', sprintf('/%s/admin/user/list', $locale));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($expected['listTitle'], $list->filter('title')->text());
        self::assertSame($expected['heading'], trim($list->filter('.card-header h6')->text()));
        self::assertSame($expected['headers'], $list->filter('#main_table thead th')->each(
            static fn (Crawler $header): string => trim($header->text()),
        ));
        self::assertStringContainsString($expected['profileLabels'], $list->filter('#main_table tbody')->text());
        self::assertStringContainsString($expected['verified'], $list->filter('#main_table tbody')->text());
        self::assertStringContainsString($expected['general'], $list->filter('#accordionSidebar')->text());

        $superAdminRow = $list->filterXPath(sprintf('//table[@id="main_table"]//tr[td[2][contains(., "%s")]]', UserFixtures::USER_SUPER_ADMIN_1_EMAIL));
        self::assertCount(1, $superAdminRow);
        self::assertStringContainsString($expected['superAdmin'], $superAdminRow->filter('td')->eq(2)->text());
        self::assertStringContainsString('ROLE_SUPER_ADMIN', $superAdminRow->filter('td')->eq(2)->text());

        $add = $client->request('GET', sprintf('/%s/admin/user/add', $locale));
        self::assertResponseIsSuccessful();
        $this->assertFormUi($add, $expected, false);
        self::assertSame(
            $expected['roles'],
            $add->filter('select[name="edit_user_form[roles][]"] option')->each(
                static fn (Crawler $option): string => trim($option->text()),
            ),
        );

        $invalid = $client->submit($add->filter('form[name="edit_user_form"]')->form([
            'edit_user_form[email]' => UserFixtures::USER_ADMIN_1_EMAIL,
        ]));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($expected['duplicate'], $invalid->filter('form')->text());

        $edit = $client->request('GET', sprintf('/%s/admin/user/edit/%d', $locale, $editableUser->getId()));
        self::assertResponseIsSuccessful();
        $this->assertFormUi($edit, $expected, true);
        self::assertSame($editableUser->getEmail(), trim($edit->filter('.form-group')->first()->filter('.col-md-11')->text()));

        $scoped = $list->filter('.card')->text().' '.$add->filter('.card')->text().' '.$invalid->filter('.card')->text().' '.$edit->filter('.card, .modal')->text();
        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $scoped);
        }
    }

    private function assertFormUi(Crawler $crawler, array $expected, bool $withModal): void
    {
        self::assertStringContainsString($expected['editTitle'], $crawler->filter('title')->text());
        $cardText = $crawler->filter('.card')->text();
        foreach ($expected['formLabels'] as $label) {
            self::assertStringContainsString($label, $cardText);
        }
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
            'listTitle' => 'Все пользователи', 'editTitle' => 'Редактирование пользователя', 'heading' => 'Пользователи',
            'headers' => ['ID', 'Электронная почта', 'Роль', 'Данные профиля', 'Электронная почта подтверждена', 'Из Google', 'Из Яндекса', 'Из VK', 'Из GitHub', ''],
            'profileLabels' => 'ФИО', 'verified' => 'Подтверждено', 'superAdmin' => 'Суперадминистратор',
            'general' => 'Общее',
            'formLabels' => ['Электронная почта', 'Новый пароль', 'ФИО', 'Телефон', 'Адрес', 'Почтовый индекс', 'Роли', 'Удалён'],
            'roles' => ['Пользователь', 'Администратор', 'Суперадминистратор'], 'save' => 'Сохранить изменения',
            'duplicate' => 'Эта электронная почта уже зарегистрирована.', 'delete' => 'Удалить запись',
            'modalTitle' => 'Вы уверены?', 'modalText' => 'Пользователь будет удалён.', 'cancel' => 'Отмена', 'close' => 'Закрыть',
        ], ['All users', 'Edit user', 'Users', 'Super Administrator', 'New password', 'This email is already registered.']];

        yield 'English' => ['en', [
            'listTitle' => 'All users', 'editTitle' => 'Edit user', 'heading' => 'Users',
            'headers' => ['ID', 'Email', 'Role', 'Profile info', 'Verified email', 'From Google', 'From Yandex', 'From VK', 'From GitHub', ''],
            'profileLabels' => 'Full name', 'verified' => 'Verified', 'superAdmin' => 'Super Administrator',
            'general' => 'General',
            'formLabels' => ['Email', 'New password', 'Full name', 'Phone', 'Address', 'Zip code', 'Roles', 'Deleted'],
            'roles' => ['User', 'Administrator', 'Super Administrator'], 'save' => 'Save changes',
            'duplicate' => 'This email is already registered.', 'delete' => 'Delete row',
            'modalTitle' => 'Are you sure?', 'modalText' => 'User will be deleted.', 'cancel' => 'Cancel', 'close' => 'Close',
        ], ['Все пользователи', 'Редактирование пользователя', 'Пользователи', 'Суперадминистратор', 'Новый пароль', 'Эта электронная почта уже зарегистрирована.']];
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createSuperAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser($this->getUser(UserFixtures::USER_SUPER_ADMIN_1_EMAIL), 'website');

        return $client;
    }
}
