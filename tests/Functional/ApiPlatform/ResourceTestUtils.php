<?php

namespace App\Tests\Functional\ApiPlatform;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;

class ResourceTestUtils extends WebTestCase
{
    protected string $uriKey = '';

    protected const REQUEST_HEADERS = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/json',
    ];

    protected const REQUEST_HEADERS_PATCH = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/merge-patch+json',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function getResponseDecodedContent(AbstractBrowser $client): array
    {
        $content = $client->getResponse()->getContent();

        self::assertIsString($content);
        self::assertJson($content);

        $decodedContent = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decodedContent);

        return $decodedContent;
    }
}
