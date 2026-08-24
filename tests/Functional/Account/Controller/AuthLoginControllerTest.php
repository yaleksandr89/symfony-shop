<?php

namespace App\Tests\Functional\Account\Controller;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Panther\PantherTestCase;

class AuthLoginControllerTest extends PantherTestCase
{
    private string $email = 'test2@test.com';
    private string $password = 'test2test2';

    #[Group(name: 'functional-panther')]
    #[TestDox('Выполняет вход через Panther')]
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

}
