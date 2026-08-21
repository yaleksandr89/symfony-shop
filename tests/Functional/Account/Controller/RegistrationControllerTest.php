<?php

namespace App\Tests\Functional\Account\Controller;

use App\Account\Message\Event\EventUserRegisteredEvent;
use App\Account\Repository\UserRepository;
use App\Account\Security\Verifier\EmailVerifier;
use App\Entity\User;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group(name: 'functional')]
class RegistrationControllerTest extends WebTestCase
{
    private static string $uniqueEmail = 'new_test_user_1@gmail.com';

    #[TestDox('Регистрация')]
    public function testRegistration(): void
    {
        $client = static::createClient();

        $newUserPassword = 'test123456';

        $client->request('GET', '/ru/registration');
        $client->submitForm('Зарегистрироваться', [
            'registration_form[email]' => self::$uniqueEmail,
            'registration_form[plainPassword]' => $newUserPassword,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/ru/', Response::HTTP_FOUND);
        $client->followRedirect();
        self::assertSelectorTextContains('div', 'Было отправлено электронное письмо. Пожалуйста, проверьте свой почтовый ящик, чтобы завершить регистрацию.');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::$uniqueEmail]);
        self::assertInstanceOf(User::class, $user);
        self::assertSame(self::$uniqueEmail, $user->getEmail());
        self::assertFalse($user->isVerified());
        self::assertNotSame($newUserPassword, $user->getPassword());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $newUserPassword));
        $userId = $user->getId();
        self::assertIsInt($userId);

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        $envelopes = $transport->get();
        self::assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(EventUserRegisteredEvent::class, $message);
        self::assertSame($userId, $message->getUserId());
    }

    #[TestDox('Повторный email при регистрации отклоняется')]
    public function testRegistrationEmailDuplicate(): void
    {
        $client = static::createClient();

        $newUserEmail = UserFixtures::USER_ADMIN_1_EMAIL;
        $newUserPassword = UserFixtures::USER_ADMIN_1_PASSWORD;

        $client->request('GET', '/ru/registration');
        $client->submitForm('Зарегистрироваться', [
            'registration_form[email]' => $newUserEmail,
            'registration_form[plainPassword]' => $newUserPassword,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('div', 'У данной электронной почты уже зарегистрирована учетная запись');
    }

    #[TestDox('Слишком короткий пароль при регистрации отклоняется')]
    public function testRegistrationPasswordToShort(): void
    {
        $client = static::createClient();

        $newUserPassword = '123';

        $client->request('GET', '/ru/registration');
        $client->submitForm('Зарегистрироваться', [
            'registration_form[email]' => self::$uniqueEmail,
            'registration_form[plainPassword]' => $newUserPassword,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('div', 'Значение слишком короткое. Должно быть равно 6 символам или больше.');
    }

    #[TestDox('Ссылка верификации подтверждает пользователя по идентификатору в запросе')]
    public function testVerificationLinkVerifiesTheUserSelectedByQueryId(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var User $user */
        $user = $container->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertNotNull($user);

        $user->setIsVerified(false);
        $container->get(EntityManagerInterface::class)->flush();

        $signedUrl = $container->get(EmailVerifier::class)
            ->generateEmailSignature('main_verify_email', $user)
            ->getSignedUrl();

        $client->request('GET', $signedUrl);

        self::assertResponseRedirects('/ru/', Response::HTTP_FOUND);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        /** @var User $verifiedUser */
        $verifiedUser = static::getContainer()->get(UserRepository::class)->find($user->getId());
        self::assertTrue($verifiedUser->isVerified());
    }
}
