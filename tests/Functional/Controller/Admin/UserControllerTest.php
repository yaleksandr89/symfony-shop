<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\User;
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
class UserControllerTest extends WebTestCase
{
    #[DataProvider(methodName: 'provideLocales')]
    #[TestDox('Суперадминистратор управляет пользователями с локализованной проверкой дубликатов')]
    public function testSuperAdminCanListAddEditAndRejectDuplicatesInSelectedLocale(
        string $locale,
        string $listTitle,
        string $editTitle,
        string $duplicateMessage,
        string $oppositeDuplicateMessage,
    ): void {
        $client = $this->createSuperAdminClient();

        $list = $client->request('GET', sprintf('/%s/admin/user/list', $locale));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($listTitle, $list->filter('title')->text());
        self::assertStringContainsString(UserFixtures::USER_SUPER_ADMIN_1_EMAIL, $list->filter('#main_table')->text());

        $add = $client->request('GET', sprintf('/%s/admin/user/add', $locale));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($editTitle, $add->filter('title')->text());
        self::assertCount(1, $add->filter('form[name="edit_user_form"]'));

        $invalid = $client->submit($add->filter('form[name="edit_user_form"]')->form([
            'edit_user_form[email]' => UserFixtures::USER_ADMIN_1_EMAIL,
        ]));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($duplicateMessage, $invalid->filter('form')->text());
        self::assertStringNotContainsString($oppositeDuplicateMessage, $invalid->filter('form')->text());

        $suffix = str_replace('.', '', uniqid('', true));
        $createdEmail = sprintf('managed-%s-%s@example.test', $locale, $suffix);
        $rawPassword = 'managed-password';
        $add = $client->request('GET', sprintf('/%s/admin/user/add', $locale));
        $client->submit($add->filter('form[name="edit_user_form"]')->form([
            'edit_user_form[email]' => $createdEmail,
            'edit_user_form[plainPassword]' => $rawPassword,
            'edit_user_form[roles]' => ['ROLE_USER'],
            'edit_user_form[fullName]' => 'Managed User',
        ]));
        self::assertResponseRedirects();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $created = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $createdEmail]);
        self::assertInstanceOf(User::class, $created);
        self::assertContains('ROLE_USER', $created->getRoles());
        self::assertNotSame($rawPassword, $created->getPassword());

        $edit = $client->request('GET', sprintf('/%s/admin/user/edit/%d', $locale, $created->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($editTitle, $edit->filter('title')->text());

        $client->submit($edit->filter('form[name="edit_user_form"]')->form([
            'edit_user_form[fullName]' => 'Updated Managed User',
        ]));
        self::assertResponseRedirects();
        $entityManager->clear();
        $updated = self::getContainer()->get(UserRepository::class)->find($created->getId());
        self::assertInstanceOf(User::class, $updated);
        self::assertSame($createdEmail, $updated->getEmail());
        self::assertSame('Updated Managed User', $updated->getFullName());
    }

    public static function provideLocales(): Generator
    {
        yield 'Russian' => ['ru', 'Все пользователи', 'Редактирование пользователя', 'Эта электронная почта уже зарегистрирована.', 'This email is already registered.'];
        yield 'English' => ['en', 'All users', 'Edit user', 'This email is already registered.', 'Эта электронная почта уже зарегистрирована.'];
    }

    #[TestDox('Обычный администратор не получает прямой доступ к управлению пользователями')]
    public function testOrdinaryAdminIsDeniedDirectUserManagementAccess(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');

        $client->request('GET', '/ru/admin/user/list');

        self::assertResponseRedirects('/ru/admin/login');
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
