<?php

namespace App\Tests\Functional\Controller\Front;

use App\Form\Front\ResetPasswordRequestFormType;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class ResetPasswordControllerTest extends WebTestCase
{
    public function testRequestFormRendersAndValidatesBlankEmail(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ru/reset-password');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Reset your password');

        $client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => '',
        ]);

        self::assertResponseIsSuccessful();

        $form = static::getContainer()->get(FormFactoryInterface::class)->create(
            ResetPasswordRequestFormType::class,
            null,
            ['csrf_protection' => false]
        );
        $form->submit(['email' => '']);

        self::assertFalse($form->isValid());
        self::assertSame('Please enter your email', $form->getErrors(true)->current()->getMessage());
    }

    public function testRequestFormRedirectsForValidEmail(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ru/reset-password');
        $client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => 'reset-request@example.test',
        ]);

        self::assertResponseRedirects('/ru/reset-password/check-email', Response::HTTP_FOUND);
    }
}
