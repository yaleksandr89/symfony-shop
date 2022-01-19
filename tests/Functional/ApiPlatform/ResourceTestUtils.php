<?php

namespace App\Tests\Functional\ApiPlatform;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;

class ResourceTestUtils extends WebTestCase
{
    /** @var string */
    protected $uriKey = '';

    protected const REQUEST_HEADERS = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/json',
    ];

    protected const REQUEST_HEADERS_PATCH = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/merge-patch+json',
    ];

    protected function getResponseDecodeContent(AbstractBrowser $client)
    {
        return json_decode($client->getResponse()->getContent());
    }
}
