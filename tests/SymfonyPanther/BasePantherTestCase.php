<?php

namespace App\Tests\SymfonyPanther;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\Panther\ProcessManager\SeleniumManager;
use Symfony\Component\Panther\ServerExtension;

class BasePantherTestCase extends PantherTestCase
{
    protected function initSeleniumClient(): Client
    {
        $seleniumServerUrl = getenv('SELENIUM_SERVER_URL') ?: 'http://127.0.0.1:4444/wd/hub';
        $seleniumServerHost = preg_replace('#/wd/hub/?$#', '', $seleniumServerUrl) ?? $seleniumServerUrl;

        static::startWebServer([
            'hostname' => getenv('PANTHER_WEB_SERVER_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('PANTHER_WEB_SERVER_PORT') ?: 9080),
        ]);

        $client = new Client(
            new SeleniumManager($seleniumServerHost, $this->getChromeCapabilities()),
            static::$baseUri
        );

        static::$pantherClients[0] = static::$pantherClient = $client;
        ServerExtension::registerClient($client);

        return $client;
    }

    private function getChromeCapabilities(): DesiredCapabilities
    {
        $chromeOptions = $this->getChromeOptions();
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        return $capabilities;
    }

    private function getChromeOptions(): ChromeOptions
    {
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments([
            '--window-size=1920,1080',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--headless', // не отображать окно браузера
        ]);

        return $chromeOptions;
    }

    protected function takeScreenshot(Client $client, string $filename): void
    {
        $preparedFilename = trim($filename);
        $preparedFilename = str_replace([' ', '/', '\\'], '-', $preparedFilename);
        $preparedFilename = strtolower($preparedFilename);

        $client->takeScreenshot('var/test-screenshot/'.$preparedFilename.'.png');
    }
}
