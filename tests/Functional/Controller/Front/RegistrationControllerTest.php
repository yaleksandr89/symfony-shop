<?php

namespace App\Tests\Functional\Controller\Front;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

/**
 * @group functional
 */
class RegistrationControllerTest extends WebTestCase
{
    private static string $uniqueEmail ='new_test_user_1@gmail.com';

    public function testRegistration(): void
    {
        $client = static::createClient();

        $newUserPassword = 'test123456';

        $client->request('GET', '/en/registration');
        $client->submitForm('Sing up', [
            'registration_form[email]' => self::$uniqueEmail,
            'registration_form[plainPassword]' => $newUserPassword,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/en/', Response::HTTP_FOUND);
        $client->followRedirect();
        self::assertSelectorTextContains('div', 'An email has been sent. Please check your inbox to complete registration.');

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

        $client->request('GET', '/en/registration');
        $client->submitForm('Sing up', [
            'registration_form[email]' => $newUserEmail,
            'registration_form[plainPassword]' => $newUserPassword,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('div', 'There is already an account with this email');
    }

    public function testRegistrationPasswordToShort(): void
    {
        $client = static::createClient();

        $newUserPassword = '123';

        $client->request('GET', '/en/registration');
        $client->submitForm('Sing up', [
            'registration_form[email]' => self::$uniqueEmail,
            'registration_form[plainPassword]' => $newUserPassword,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('div', 'This value is too short. It should have 6 characters or more.');
    }
}
