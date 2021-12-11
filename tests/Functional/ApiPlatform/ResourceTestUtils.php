<?php

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;

class ResourceTestUtils extends WebTestCase
{
    /** @var string */
    protected $uriKey = '';

    protected const REQUEST_HEADERS = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/json'
    ];

    protected const REQUEST_HEADERS_PATCH = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/merge-patch+json'
    ];
    protected function getResponseDecodeContent(AbstractBrowser $client)
    {
        return json_decode(
            $client->getResponse()->getContent()
        );
    }

    protected function checkDefaultUserHasNotAccess(AbstractBrowser $client, string $uri, string $method)
    {
        /** @var User $user */
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);

        $client->loginUser($user, 'website');
        $client->request($method, $uri, [], [], self::REQUEST_HEADERS, json_encode([]));
        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $client->restart();
    }
}