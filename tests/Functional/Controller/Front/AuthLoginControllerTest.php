<?php

namespace App\Tests\Functional\Controller\Front;

use App\Repository\UserRepository;
use App\Tests\SymfonyPanther\BasePantherTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthLoginControllerTest extends BasePantherTestCase
{
    private string $email = 'test3@test.com';
    private string $password = 'test3test3';

    public function testLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ru/login');
        $client->submitForm('Авторизоваться', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    /**
     * @group functional-panther
     */
    public function testLoginWithPantherClient(): void
    {
        $client = static::createPantherClient(['browser' => self::CHROME]);
        $client->request('GET', '/ru/login');

        $client->submitForm('Авторизоваться', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        self::assertSame(self::$baseUri.'/ru/profile', $client->getCurrentURL());
        self::assertPageTitleContains('Добро пожаловать в ЛК');
        self::assertSelectorTextContains('#page_header_title', 'Добро пожаловать в ЛК!');
    }

    /**
     * @group functional-selenium
     */
    public function testLoginWithSeleniumClient(): void
    {
        $client = $this->initSeleniumClient();
        $client->request('GET', '/ru/login');

        $crawler = $client->submitForm('Авторизоваться', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        //sleep(3);
        $this->takeScreenshot($client, ' App\Tests\Functional\Controller\Front ');

        self::assertSame(
            $crawler->filter('#page_header_title')->text(),
            'Добро пожаловать в ЛК!'
        );
    }
}
