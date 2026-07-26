<?php

declare(strict_types=1);

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Utils\Generator\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
class ProductImageResourceTest extends ResourceTestUtils
{
    private const COLLECTION_URI = '/api/product_images';

    public function testStandaloneReadRoutesAndOpenApiPathsAreAbsentWhileCartImagesRemainEmbedded(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        $routes = $router->getRouteCollection();

        self::assertNull($routes->get('api_product_images_get_collection'));
        self::assertNull($routes->get('api_product_images_get_item'));

        $client->request('GET', '/api/docs.json', [], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $document = $this->getResponseDecodedContent($client);
        self::assertArrayNotHasKey(self::COLLECTION_URI, $document['paths']);
        self::assertArrayNotHasKey(self::COLLECTION_URI.'/{id}', $document['paths']);

        $context = $this->createCartImageContext();
        $imageId = $context['image']->getId();
        $cartId = $context['cart']->getId();
        self::assertNotNull($imageId);
        self::assertNotNull($cartId);

        $client->request('GET', self::COLLECTION_URI, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('GET', self::COLLECTION_URI.'/'.$imageId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $context['token']));
        $client->request('GET', '/api/carts/'.$cartId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $cartDocument = $this->getResponseDecodedContent($client);
        self::assertIsArray($cartDocument['cartProducts']);
        self::assertCount(1, $cartDocument['cartProducts']);
        $productDocument = $cartDocument['cartProducts'][0]['product'];
        self::assertIsArray($productDocument);
        self::assertIsArray($productDocument['productImages']);
        self::assertCount(1, $productDocument['productImages']);
        $imageDocument = $productDocument['productImages'][0];
        self::assertSame($imageId, $imageDocument['id']);
        self::assertSame('cart-image_small.jpg', $imageDocument['filenameSmall']);
        self::assertArrayHasKey('@id', $imageDocument);
        self::assertIsString($imageDocument['@id']);
        self::assertStringNotContainsString(self::COLLECTION_URI.'/', $imageDocument['@id']);
        self::assertArrayNotHasKey('product', $imageDocument);
        self::assertArrayNotHasKey('filenameBig', $imageDocument);
        self::assertArrayNotHasKey('filenameMiddle', $imageDocument);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', TokenGenerator::generateToken()));
        $client->request('GET', '/api/carts/'.$cartId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->getCookieJar()->clear();
        $client->request('GET', '/api/carts/'.$cartId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /** @return array{cart: Cart, image: ProductImage, token: string} */
    private function createCartImageContext(): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $product = (new Product())
            ->setTitle('Cart image product '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished(true);
        $image = (new ProductImage())
            ->setFilenameBig('cart-image_big.jpg')
            ->setFilenameMiddle('cart-image_middle.jpg')
            ->setFilenameSmall('cart-image_small.jpg');
        $product->addProductImage($image);

        $token = TokenGenerator::generateToken();
        $cart = (new Cart())->setToken($token);
        $cart->addCartProduct((new CartProduct())->setProduct($product)->setQuantity(1));

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($product);
        $entityManager->persist($cart);
        $entityManager->flush();

        return ['cart' => $cart, 'image' => $image, 'token' => $token];
    }
}
