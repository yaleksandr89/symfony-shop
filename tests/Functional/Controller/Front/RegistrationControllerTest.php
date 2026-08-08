<?php

namespace App\Tests\Functional\Controller\Front;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Verifier\EmailVerifier;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

#[Group(name: 'functional')]
class RegistrationControllerTest extends WebTestCase
{
    private static string $uniqueEmail = 'new_test_user_1@gmail.com';

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

        /** @var User $user */
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::$uniqueEmail]);
        self::assertNotNull($user);
        self::assertSame(self::$uniqueEmail, $user->getEmail());

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertCount(1, $transport->get());
    }

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
