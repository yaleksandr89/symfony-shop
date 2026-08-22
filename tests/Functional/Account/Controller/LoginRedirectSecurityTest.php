<?php

declare(strict_types=1);

namespace App\Tests\Functional\Account\Controller;

use App\Account\Security\Authenticator\LoginFormAuthenticator;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
final class LoginRedirectSecurityTest extends WebTestCase
{
    #[TestDox('Внешний Referer с окончанием cart игнорируется после успешного входа')]
    public function testExternalCartRefererIsIgnored(): void
    {
        $client = self::createClient();
        $client->request('GET', '/ru/login', [], [], [
            'HTTP_REFERER' => 'https://attacker.example/cart',
        ]);

        $client->submitForm('Авторизоваться', $this->credentials());

        $expectedProfileUrl = $this->router()->generate('main_profile_index', ['_locale' => 'ru']);
        self::assertResponseRedirects($expectedProfileUrl, Response::HTTP_FOUND);
        self::assertStringNotContainsString('attacker.example', (string) $client->getResponse()->headers->get('location'));
    }

    #[TestDox('Разрешённое намерение возврата из корзины ведёт на локальную корзину и потребляется')]
    public function testCartReturnIntentRedirectsLocallyAndIsConsumed(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/login?return=cart');

        $client->submitForm('Log in', $this->credentials());

        $expectedCartUrl = $this->router()->generate('main_cart_show', ['_locale' => 'en']);
        self::assertResponseRedirects($expectedCartUrl, Response::HTTP_FOUND);
        self::assertFalse($client->getRequest()->getSession()->has(LoginFormAuthenticator::RETURN_INTENT_SESSION_KEY));
    }

    #[TestDox('Сохранённый Symfony TargetPath имеет приоритет над намерением возврата в корзину')]
    public function testSavedTargetPathTakesPrecedenceOverCartReturnIntent(): void
    {
        $client = self::createClient();
        $loginPage = $client->request('GET', '/ru/login?return=cart');
        $loginForm = $loginPage->selectButton('Авторизоваться')->form($this->credentials());

        $client->request('GET', '/ru/profile');
        $savedTargetPath = $client->getRequest()->getUri();
        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);

        $client->submit($loginForm);

        $cartUrl = $this->router()->generate('main_cart_show', ['_locale' => 'ru']);
        self::assertResponseRedirects($savedTargetPath, Response::HTTP_FOUND);
        self::assertNotSame($cartUrl, $client->getResponse()->headers->get('location'));
        self::assertFalse($client->getRequest()->getSession()->has(LoginFormAuthenticator::RETURN_INTENT_SESSION_KEY));
    }

    /** @return array{email: string, password: string} */
    private function credentials(): array
    {
        return [
            'email' => UserFixtures::USER_1_EMAIL,
            'password' => 'test3test3',
        ];
    }

    private function router(): RouterInterface
    {
        return self::getContainer()->get(RouterInterface::class);
    }
}
