<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserChecker\DeletedUserChecker;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

#[Group(name: 'functional')]
class DeletedUserAuthenticationTest extends WebTestCase
{
    #[TestDox('Оба firewall используют проверку удалённого пользователя')]
    public function testBothFirewallsUseTheDeletedUserChecker(): void
    {
        foreach (['security.user_checker.admin', 'security.user_checker.front'] as $serviceId) {
            $checker = self::getContainer()->get($serviceId);
            self::assertInstanceOf(DeletedUserChecker::class, $checker);

            try {
                $checker->checkPreAuth((new User())->setIsDeleted(true));
                self::fail(sprintf('%s does not use the deleted-user checker.', $serviceId));
            } catch (CustomUserMessageAccountStatusException $exception) {
                self::assertSame('Invalid credentials.', $exception->getMessageKey());
            }
        }
    }

    #[TestDox('Активные пользователи по-прежнему входят через фронтальную и административную формы')]
    public function testActiveUsersCanStillLogInThroughFrontAndAdminForms(): void
    {
        $frontClient = static::createClient();
        $this->submitFrontLogin($frontClient, UserFixtures::USER_1_EMAIL, 'test3test3');
        self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);

        $this->submitAdminLogin($frontClient, UserFixtures::USER_ADMIN_1_EMAIL, 'test2test2');
        self::assertResponseRedirects('/ru/admin/dashboard', Response::HTTP_FOUND);
    }

    #[TestDox('Удалённый фронтальный пользователь получает ту же нейтральную ошибку, что и при неверном пароле')]
    public function testDeletedFrontUserGetsTheSameGenericFailureAsAnInvalidPassword(): void
    {
        $client = static::createClient();
        $invalidPasswordMessage = $this->frontFailureMessage($client, UserFixtures::USER_1_EMAIL, 'wrong-password');
        $this->softDelete(UserFixtures::USER_1_EMAIL);

        $client->getCookieJar()->clear();
        $deletedUserMessage = $this->frontFailureMessage($client, UserFixtures::USER_1_EMAIL, 'test3test3');

        self::assertSame($invalidPasswordMessage, $deletedUserMessage);
        $this->assertGenericFailureMessage($deletedUserMessage, UserFixtures::USER_1_EMAIL);
    }

    #[TestDox('Удалённый администратор получает ту же нейтральную ошибку, что и при неверном пароле')]
    public function testDeletedAdminUserGetsTheSameGenericFailureAsAnInvalidPassword(): void
    {
        $client = static::createClient();
        $invalidPasswordMessage = $this->adminFailureMessage($client, UserFixtures::USER_ADMIN_1_EMAIL, 'wrong-password');
        $this->softDelete(UserFixtures::USER_ADMIN_1_EMAIL);

        $client->getCookieJar()->clear();
        $deletedUserMessage = $this->adminFailureMessage($client, UserFixtures::USER_ADMIN_1_EMAIL, 'test2test2');

        self::assertSame($invalidPasswordMessage, $deletedUserMessage);
        $this->assertGenericFailureMessage($deletedUserMessage, UserFixtures::USER_ADMIN_1_EMAIL);
    }

    #[TestDox('Существующая сессия деаутентифицируется после мягкого удаления пользователя')]
    public function testExistingSessionIsDeauthenticatedAfterUserIsSoftDeleted(): void
    {
        $client = static::createClient();
        $this->submitFrontLogin($client, UserFixtures::USER_1_EMAIL, 'test3test3');
        self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);

        $this->softDelete(UserFixtures::USER_1_EMAIL);
        $client->request('GET', '/ru/profile');

        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);
    }

    #[TestDox('Удалённый фронтальный пользователь не восстанавливается из cookie «запомнить меня»')]
    public function testDeletedFrontUserCannotBeRestoredFromRememberMeCookie(): void
    {
        $client = static::createClient();
        $cookie = $this->issueFrontRememberMeCookie($client);
        $this->softDelete(UserFixtures::USER_1_EMAIL);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($cookie);
        $client->request('GET', '/ru/profile');

        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);
    }

    #[TestDox('Удалённый администратор не восстанавливается из cookie «запомнить меня»')]
    public function testDeletedAdminUserCannotBeRestoredFromRememberMeCookie(): void
    {
        $client = static::createClient();
        $cookie = $this->issueAdminRememberMeCookie($client);
        $this->softDelete(UserFixtures::USER_ADMIN_1_EMAIL);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($cookie);
        $client->request('GET', '/ru/admin/dashboard');

        self::assertResponseRedirects('/ru/admin/login', Response::HTTP_FOUND);
    }

    private function frontFailureMessage(KernelBrowser $client, string $email, string $password): string
    {
        $this->submitFrontLogin($client, $email, $password);

        return $this->getAuthenticationFailureMessage($client);
    }

    private function adminFailureMessage(KernelBrowser $client, string $email, string $password): string
    {
        $this->submitAdminLogin($client, $email, $password);

        return $this->getAuthenticationFailureMessage($client);
    }

    private function submitFrontLogin(KernelBrowser $client, string $email, string $password, bool $rememberMe = false): void
    {
        $client->request('GET', '/ru/login');
        $client->submitForm('Авторизоваться', [
            'email' => $email,
            'password' => $password,
            '_remember_me' => $rememberMe,
        ]);
    }

    private function submitAdminLogin(KernelBrowser $client, string $email, string $password, bool $rememberMe = false): void
    {
        $client->request('GET', '/ru/admin/login');
        $client->submitForm('Войти', [
            'email' => $email,
            'password' => $password,
            '_remember_me' => $rememberMe,
        ]);
    }

    private function issueFrontRememberMeCookie(KernelBrowser $client): Cookie
    {
        $this->submitFrontLogin($client, UserFixtures::USER_1_EMAIL, 'test3test3', true);
        self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);

        $cookie = $client->getCookieJar()->get('REMEMBERME');
        self::assertInstanceOf(Cookie::class, $cookie);

        return $cookie;
    }

    private function issueAdminRememberMeCookie(KernelBrowser $client): Cookie
    {
        $this->submitAdminLogin($client, UserFixtures::USER_ADMIN_1_EMAIL, 'test2test2', true);
        self::assertResponseRedirects('/ru/admin/dashboard', Response::HTTP_FOUND);

        $cookie = $client->getCookieJar()->get('ADMIN_REMEMBERME');
        self::assertInstanceOf(Cookie::class, $cookie);

        return $cookie;
    }

    private function softDelete(string $email): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getUser($email);
        $user->setIsDeleted(true);
        $entityManager->flush();
        $entityManager->clear();
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function assertGenericFailureMessage(string $message, string $email): void
    {
        self::assertSame('Недействительные аутентификационные данные.', $message);
        self::assertStringNotContainsString('deleted', strtolower($message));
        self::assertStringNotContainsString('disabled', strtolower($message));
        self::assertStringNotContainsString(strtolower($email), strtolower($message));
    }

    private function getAuthenticationFailureMessage(KernelBrowser $client): string
    {
        return trim((string) preg_replace('/\s*×$/u', '', $client->getCrawler()->filter('.alert-danger')->text()));
    }
}
