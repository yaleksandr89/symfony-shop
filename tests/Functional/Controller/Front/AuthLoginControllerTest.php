<?php

namespace App\Tests\Functional\Controller\Front;

use App\Tests\SymfonyPanther\BasePantherTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;

class AuthLoginControllerTest extends BasePantherTestCase
{
    private string $email = 'test2@test.com';
    private string $password = 'test2test2';

    #[Group(name: 'functional-panther')]
    public function testLoginWithPantherClient(): void
    {
        $client = static::createPantherClient(['browser' => self::CHROME]);
        $client->request('GET', '/ru/login');
        $client->submitForm('Авторизоваться', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $crawler = $client->waitForElementToContain(
            '#page_header_title',
            'Добро пожаловать в ЛК!'
        );

        self::assertSame(self::$baseUri.'/ru/profile', $client->getCurrentURL());
        self::assertPageTitleContains('Добро пожаловать в ЛК');
        self::assertSame('Добро пожаловать в ЛК!', $crawler->filter('#page_header_title')->text());
    }

    #[Group(name: 'functional-selenium')]
    public function testLoginWithSeleniumClient(): void
    {
        $client = $this->initSeleniumClient();
        $client->request('GET', '/ru/login');

        $client->submitForm('Авторизоваться', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $crawler = $client->waitFor('#page_header_title');

        // sleep(10); // раскомментировать, если нужно увидеть, что отображается на экране
        $this->takeScreenshot($client, ' App\Tests\Functional\Controller\Front ');

        self::assertSame('Добро пожаловать в ЛК!', $crawler->filter('#page_header_title')->text());
    }
}
