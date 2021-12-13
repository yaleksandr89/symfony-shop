<?php

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;

/**
 * @group functional
 */
class ProductResourceTest extends ResourceTestUtils
{
    /** @var string */
    protected $uriKey = '/api/products';

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

        $uri = $this->uriKey.'/'.$product->getUuid();

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

    public function testPathProduct()
    {
        $client = self::createClient();

        /** @var Product $product */
        $product = self::getContainer()->get(ProductRepository::class)->findOneBy([]);
        $uri = $this->uriKey.'/'.$product->getUuid();

        $this->checkDefaultUserHasNotAccess($client, $uri, 'PATCH');

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_ADMIN_1_EMAIL]);
        $client->loginUser($user, 'website');

        $context = [
            'title' => 'Update product',
        ];

        $client->request('PATCH', $uri, [], [], self::REQUEST_HEADERS_PATCH, json_encode($context));

        self::assertResponseStatusCodeSame(200);
    }
}
