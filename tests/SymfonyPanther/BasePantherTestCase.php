<?php

namespace App\Tests\SymfonyPanther;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

class BasePantherTestCase extends PantherTestCase
{
    /**
     * @return Client
     */
    protected function initSeleniumClient(): Client
    {
        static::createPantherClient();
        static::startWebServer();

        $capabilities = $this->getChromeCapabilities();
        return Client::createSeleniumClient('http://127.0.0.1:4444/wd/hub', $capabilities, 'http://127.0.0.1:9080');
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
            // '--headless', // не отображать окно браузера
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
