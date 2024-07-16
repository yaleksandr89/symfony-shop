<?php

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class ProductResourceTest extends ResourceTestUtils
{
    /** @var string */
    protected string $uriKey = '/api/products';

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

    public function testCreatedProductWithAccess(): void
    {
        $client = self::createClient();

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_ADMIN_1_EMAIL]);
        $client->loginUser($user, 'website');

        $context = [
            'title' => 'New product',
            'price' => '100',
            'quantity' => 5,
        ];

        $client->request('POST', $this->uriKey, [], [], self::REQUEST_HEADERS, json_encode($context));

        self::assertResponseStatusCodeSame(201);
    }

    public function testCreatedProductWithoutAccess(): void
    {
        $client = self::createClient();

        /** @var User $user */
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        $client->loginUser($user, 'website');

        $context = [
            'title' => 'New product',
            'price' => 'INCORRECT VALUE',
            'quantity' => 5,
        ];

        $client->request('POST', $this->uriKey, [], [], self::REQUEST_HEADERS, json_encode([$context]));

        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);
        $client->followRedirect();
    }

    public function testPathProductWithAccess(): void
    {
        $client = self::createClient();

        /** @var Product $product */
        $product = self::getContainer()->get(ProductRepository::class)->findOneBy([]);

        /** @var User $user */
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_ADMIN_1_EMAIL]);

        $client->loginUser($user, 'website');

        $uri = $this->uriKey.'/'.$product->getUuid();
        $context = [
            'title' => 'Update product',
        ];

        $client->request('PATCH', $uri, [], [], self::REQUEST_HEADERS_PATCH, json_encode($context));

        self::assertResponseStatusCodeSame(200);
    }

    public function testPathProductWithoutAccess(): void
    {
        $client = self::createClient();

        /** @var Product $product */
        $product = self::getContainer()->get(ProductRepository::class)->findOneBy([]);

        /** @var User $user */
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);

        $client->loginUser($user, 'website');

        $uri = $this->uriKey.'/'.$product->getUuid();
        $context = [
            'title' => 'Update product',
        ];

        $client->request('PATCH', $uri, [], [], self::REQUEST_HEADERS_PATCH, json_encode($context));

        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);
        $client->followRedirect();
    }
}
