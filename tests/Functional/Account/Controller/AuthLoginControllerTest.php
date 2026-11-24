<?php

declare(strict_types=1);

namespace App\Tests\Functional\Account\Controller;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

class AuthLoginControllerTest extends PantherTestCase
{
    private string $email = 'test2@test.com';
    private string $password = 'test2test2';

    #[Group('functional-panther')]
    #[TestDox('Флажки доступны на широком и узком экранах, ошибку можно закрыть и повторить вход')]
    public function testLoginWithPantherClient(): void
    {
        $client = static::createPantherClient(['browser' => self::CHROME]);
        $driver = $client->getWebDriver();
        foreach ([new WebDriverDimension(1280, 900), new WebDriverDimension(390, 844)] as $viewport) {
            $driver->manage()->window()->setSize($viewport);
            $client->request('GET', '/ru/registration');
            $this->assertCheckboxInteraction(
                $client,
                'registration_form_agreeTerms',
                'registration_form[agreeTerms]',
                'form button[type="submit"]',
            );

            $client->request('GET', '/ru/login');
            $this->assertCheckboxInteraction($client, 'rememberMe', '_remember_me', '#inputPassword');
        }

        $client->submitForm('Авторизоваться', [
            'email' => $this->email,
            'password' => 'invalid-password-for-test',
        ]);
        $alertSelector = '#form [role="alert"].alert-danger';
        $alert = $client->waitForVisibility($alertSelector);
        self::assertNotSame('', trim($alert->filter($alertSelector)->text()));
        $driver->findElement(WebDriverBy::cssSelector($alertSelector . ' button.btn-close'))->click();
        $client->waitForInvisibility($alertSelector);
        self::assertCount(0, $driver->findElements(WebDriverBy::cssSelector($alertSelector)));

        $driver->findElement(WebDriverBy::cssSelector('label[for="rememberMe"]'))->click();
        $client->submitForm('Авторизоваться', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $crawler = $client->waitForElementToContain(
            '#page_header_title',
            'Добро пожаловать в ЛК!',
        );

        self::assertSame(self::$baseUri . '/ru/profile', $client->getCurrentURL());
        self::assertPageTitleContains('Добро пожаловать в ЛК');
        self::assertSame('Добро пожаловать в ЛК!', $crawler->filter('#page_header_title')->text());
    }

    private function assertCheckboxInteraction(Client $client, string $id, string $name, string $previousControl): void
    {
        $driver = $client->getWebDriver();
        $checkbox = $driver->findElement(WebDriverBy::id($id));
        $label = $driver->findElement(WebDriverBy::cssSelector(sprintf('label[for="%s"]', $id)));
        self::assertSame($name, $checkbox->getAttribute('name'));
        self::assertTrue($checkbox->isDisplayed());
        self::assertFalse($checkbox->isSelected());
        self::assertTrue($client->executeScript(<<<'JS'
            const [input, label] = arguments;
            const box = input.getBoundingClientRect();
            const text = label.getBoundingClientRect();
            const style = getComputedStyle(input);
            return label.control === input && box.width >= 12 && box.height >= 12
                && box.right < text.left && Number(style.opacity) > 0
                && parseFloat(style.borderLeftWidth) > 0;
            JS, [$checkbox, $label]), sprintf('Checkbox and label must be visible without overlap (%s).', $id));

        $driver->findElement(WebDriverBy::cssSelector($previousControl))->sendKeys(WebDriverKeys::TAB);
        self::assertTrue($client->executeScript(<<<'JS'
            const input = arguments[0], style = getComputedStyle(input);
            return document.activeElement === input && input.matches(':focus-visible')
                && style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) >= 2;
            JS, [$checkbox]), sprintf('Tab must move to a visible focus indicator (%s).', $id));

        $checkbox->sendKeys(WebDriverKeys::SPACE);
        self::assertTrue($checkbox->isSelected());
        self::assertTrue($client->executeScript(
            'return getComputedStyle(arguments[0]).backgroundImage !== "none";',
            [$checkbox],
        ));
        $label->click();
        self::assertFalse($checkbox->isSelected());
        $label->click();
        self::assertTrue($checkbox->isSelected());
        $label->click();
        self::assertFalse($checkbox->isSelected());
    }
}
