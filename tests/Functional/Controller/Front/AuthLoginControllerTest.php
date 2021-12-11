<?php

namespace App\Tests\Functional\Controller\Front;

use App\Tests\SymfonyPanther\BasePantherTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthLoginControllerTest extends BasePantherTestCase
{
    private string $email = 'test3@test.com';
    private string $password = 'test3test3';

    public function testLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/login');
        $client->submitForm('Log in', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        self::assertResponseRedirects('/en/profile', Response::HTTP_FOUND);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    /**
     * @group functional-panther
     */
    public function testLoginWithPantherClient(): void
    {
        $client = static::createPantherClient(['browser' => self::CHROME]);

        $client->request('GET', '/en/login');
        $client->submitForm('Log in', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        self::assertSame(self::$baseUri . '/en/profile', $client->getCurrentURL());
        self::assertPageTitleContains('Welcome, to your profile');
        self::assertSelectorTextContains('#page_header_title', 'Welcome, to your profile!');
    }

    /**
     * @group functional-selenium
     */
    public function testLoginWithSeleniumClient(): void
    {
        $client = $this->initSeleniumClient();

        $client->request('GET', '/en/login');
        $crawler = $client->submitForm('Log in', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        //sleep(3);
        $this->takeScreenshot($client, ' App\Tests\Functional\Controller\Front ');

        self::assertSame(
            $crawler->filter('#page_header_title')->text(),
            'Welcome, to your profile!'
        );
    }
}
