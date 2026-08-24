<?php

declare(strict_types=1);

namespace App\Tests\Functional\Account\Controller;

use App\Account\Message\Command\ResetUserPasswordCommand;
use App\Account\Repository\ResetPasswordRequestRepository;
use App\Account\Repository\UserRepository;
use App\Entity\User;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Group(name: 'functional')]
class ResetPasswordControllerTest extends WebTestCase
{
    #[TestDox('Пустой email остаётся на форме и не ставит команду сброса в очередь')]
    public function testBlankEmailStaysOnFormWithoutQueueingResetCommand(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ru/reset-password');
        $client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => '',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="reset_password_request_form"]');
        self::assertSelectorExists('input[name="reset_password_request_form[email]"]');
        self::assertSelectorTextContains('form[name="reset_password_request_form"]', 'Please enter your email');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertCount(0, $transport->get());
    }

    #[DataProvider('knownAndUnknownEmails')]
    #[TestDox('Известный и неизвестный email получают нейтральный редирект и команду сброса')]
    public function testValidRequestQueuesCommandAndUsesNeutralRedirect(string $email): void
    {
        $client = static::createClient();

        $client->request('GET', '/ru/reset-password');
        $client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => $email,
        ]);

        self::assertResponseRedirects('/ru/reset-password/check-email', Response::HTTP_FOUND);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $envelopes = $transport->get();
        self::assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(ResetUserPasswordCommand::class, $message);
        self::assertSame($email, $message->getEmail());
    }

    /** @return iterable<string, array{string}> */
    public static function knownAndUnknownEmails(): iterable
    {
        yield 'known account' => [UserFixtures::USER_1_EMAIL];
        yield 'unknown account' => ['unknown-reset-request@example.test'];
    }

    #[TestDox('Известный и неизвестный email получают одинаковую нейтральную check-email страницу')]
    public function testKnownAndUnknownRequestsShowSameNeutralCheckEmailPage(): void
    {
        $knownPage = $this->requestCheckEmailPage(UserFixtures::USER_1_EMAIL);
        $unknownEmail = 'unknown-reset-request@example.test';
        $unknownPage = $this->requestCheckEmailPage($unknownEmail);

        self::assertSame($knownPage, $unknownPage);
        self::assertStringNotContainsString(UserFixtures::USER_1_EMAIL, $knownPage['content']);
        self::assertStringNotContainsString($unknownEmail, $unknownPage['content']);
    }

    #[TestDox('Прямой check-email GET без token-состояния безопасно показывает нейтральную страницу')]
    public function testDirectCheckEmailGetWithoutTokenStateIsSafeAndNeutral(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/reset-password/check-email');

        self::assertResponseIsSuccessful();
        $page = $this->neutralPageSignature($crawler);
        self::assertSame('Reset your password', $page['heading']);
        self::assertStringContainsString('If an account matching your email exists', $page['content']);
        self::assertStringNotContainsString(UserFixtures::USER_1_EMAIL, $page['content']);
    }

    #[TestDox('Валидный token одноразово меняет пароль и удаляет reset request')]
    public function testValidTokenChangesPasswordAndCannotBeReplayed(): void
    {
        $client = static::createClient();
        $user = $this->getFixtureUser();
        $userId = (int) $user->getId();
        $token = self::getContainer()->get(ResetPasswordHelperInterface::class)->generateResetToken($user)->getToken();
        $newPassword = 'new-reset-password';

        $client->request('GET', '/ru/reset-password/reset/'.$token);
        self::assertResponseRedirects('/ru/reset-password/reset', Response::HTTP_FOUND);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->submit($crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[plainPassword][first]' => $newPassword,
            'change_password_form[plainPassword][second]' => $newPassword,
        ]));

        self::assertResponseRedirects('/ru/', Response::HTTP_FOUND);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $persistedUser = $entityManager->find(User::class, $userId);
        self::assertInstanceOf(User::class, $persistedUser);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertFalse($hasher->isPasswordValid($persistedUser, 'test3test3'));
        self::assertTrue($hasher->isPasswordValid($persistedUser, $newPassword));
        self::assertSame(0, self::getContainer()->get(ResetPasswordRequestRepository::class)->count([
            'user' => $persistedUser,
        ]));

        $client->request('GET', '/ru/reset-password/reset/'.$token);
        self::assertResponseRedirects('/ru/reset-password/reset', Response::HTTP_FOUND);
        $client->followRedirect();
        self::assertResponseRedirects('/ru/reset-password', Response::HTTP_FOUND);
    }

    #[TestDox('Некорректный token отклоняется без показа формы нового пароля')]
    public function testInvalidTokenIsRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ru/reset-password/reset/'.str_repeat('0', 40));
        self::assertResponseRedirects('/ru/reset-password/reset', Response::HTTP_FOUND);
        $client->followRedirect();

        self::assertResponseRedirects('/ru/reset-password', Response::HTTP_FOUND);
    }

    #[TestDox('Истёкший token отклоняется детерминированно')]
    public function testExpiredTokenIsRejectedDeterministically(): void
    {
        $client = static::createClient();
        $token = self::getContainer()->get(ResetPasswordHelperInterface::class)
            ->generateResetToken($this->getFixtureUser(), -1)
            ->getToken();

        $client->request('GET', '/ru/reset-password/reset/'.$token);
        self::assertResponseRedirects('/ru/reset-password/reset', Response::HTTP_FOUND);
        $client->followRedirect();

        self::assertResponseRedirects('/ru/reset-password', Response::HTTP_FOUND);
    }

    #[TestDox('Сбой flush откатывает удаление token и изменение пароля')]
    public function testFlushFailureRollsBackPasswordAndResetRequestDeletion(): void
    {
        $client = static::createClient();
        $user = $this->getFixtureUser();
        $userId = (int) $user->getId();
        $token = self::getContainer()->get(ResetPasswordHelperInterface::class)->generateResetToken($user)->getToken();
        $newPassword = 'rolled-back-password';

        $client->request('GET', '/ru/reset-password/reset/'.$token);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $eventManager = $entityManager->getEventManager();
        $listener = new class($entityManager->getConnection(), $userId) {
            public bool $resetRequestDeletionObserved = false;

            public function __construct(private Connection $connection, private int $userId)
            {
            }

            public function preFlush(PreFlushEventArgs $eventArgs): void
            {
                $this->resetRequestDeletionObserved = 0 === (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM reset_password_request WHERE user_id = ?',
                    [$this->userId],
                );

                throw new \RuntimeException('Forced reset password flush failure.');
            }
        };
        $eventManager->addEventListener([Events::preFlush], $listener);

        try {
            $client->submit($crawler->filter('form[name="change_password_form"]')->form([
                'change_password_form[plainPassword][first]' => $newPassword,
                'change_password_form[plainPassword][second]' => $newPassword,
            ]));
        } finally {
            $eventManager->removeEventListener([Events::preFlush], $listener);
        }

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertTrue($listener->resetRequestDeletionObserved);

        static::ensureKernelShutdown();
        static::bootKernel();
        $freshEntityManager = self::getContainer()->get(EntityManagerInterface::class);
        $freshUser = $freshEntityManager->find(User::class, $userId);
        self::assertInstanceOf(User::class, $freshUser);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($freshUser, 'test3test3'));
        self::assertFalse($hasher->isPasswordValid($freshUser, $newPassword));
        self::assertSame(1, self::getContainer()->get(ResetPasswordRequestRepository::class)->count([
            'user' => $freshUser,
        ]));
        $tokenUser = self::getContainer()->get(ResetPasswordHelperInterface::class)->validateTokenAndFetchUser($token);
        self::assertInstanceOf(User::class, $tokenUser);
        self::assertSame($userId, $tokenUser->getId());
    }

    private function getFixtureUser(): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => UserFixtures::USER_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    /** @return array{title: string, heading: string, paragraphs: int, content: string} */
    private function requestCheckEmailPage(string $email): array
    {
        $client = static::createClient();
        $client->request('GET', '/ru/reset-password');
        $client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => $email,
        ]);

        self::assertResponseRedirects('/ru/reset-password/check-email', Response::HTTP_FOUND);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        $page = $this->neutralPageSignature($crawler);
        static::ensureKernelShutdown();

        return $page;
    }

    /** @return array{title: string, heading: string, paragraphs: int, content: string} */
    private function neutralPageSignature(Crawler $crawler): array
    {
        return [
            'title' => trim($crawler->filter('title')->text()),
            'heading' => trim($crawler->filter('.page-login h1')->text()),
            'paragraphs' => $crawler->filter('.page-login p')->count(),
            'content' => trim((string) preg_replace('/\s+/u', ' ', $crawler->filter('.page-login')->text())),
        ];
    }
}
