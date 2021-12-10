<?php

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group functional
 */
class ProductResourceTest extends WebTestCase
{
    /** @var string */
    protected $uriKey = '/api/products';

    public const REQUEST_HEADERS = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/json'
    ];

    public function testGetProducts(): void
    {
        $client = self::createClient();

        $client->request('GET', $this->uriKey, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(200);
    }

    public function testGetProduct(): void
    {
        $client = self::createClient();

        /** @var Product $product */
        $product = self::getContainer()->get(ProductRepository::class)->findOneBy([]);

        $uri = $this->uriKey . '/' . $product->getUuid();

        $client->request('GET', $uri, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(200);
    }

    public function testCreatedProduct()
    {
        $client = self::createClient();

        $this->checkDefaultUserHasNotAccess($client, $this->uriKey, 'POST');

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_ADMIN_1_EMAIL]);
        $client->loginUser($user, 'website');

        $context = [
            'title' => 'New product',
            'price' => 'New 100',
            'quantity' => 5,
        ];

        $client->request('POST', $this->uriKey, [], [], self::REQUEST_HEADERS, json_encode($context));

        self::assertResponseStatusCodeSame(201);
    }

    public function getResponseDecodeContent(AbstractBrowser $client)
    {
        return json_decode(
            $client->getResponse()->getContent()
        );
    }

    public function checkDefaultUserHasNotAccess(AbstractBrowser $client, string $uri, string $method)
    {
        /** @var User $user */
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);

        $client->loginUser($user, 'website');
        $client->request($method, $uri, [], [], self::REQUEST_HEADERS, json_encode([]));
        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $client->restart();
    }
}
