<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Tests\SymfonyPanther\BasePantherTestCase;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use App\Utils\Generator\TokenGenerator;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Panther\Client;

class CartCheckoutUnavailableBrowserTest extends BasePantherTestCase
{
    #[Group(name: 'functional-panther')]
    #[TestDox('Покупатель через интерфейс удаляет недоступные товары и оформляет оставшийся')]
    public function testCheckoutHighlightsUnavailableProductsUntilTheyAreRemoved(): void
    {
        $client = static::createPantherClient(['browser' => self::CHROME]);
        $client->restart();
        $context = null;
        $testFailure = null;

        try {
            $context = $this->createCartContext();
            $this->logInAndOpenCart($client, $context['token']);

            $activeRow = $this->cartProductSelector($context['activeCartProductId']);
            $unpublishedRow = $this->cartProductSelector($context['unpublishedCartProductId']);
            $deletedRow = $this->cartProductSelector($context['deletedCartProductId']);
            $client->waitFor($activeRow);
            $client->waitFor($unpublishedRow);
            $client->waitFor($deletedRow);
            self::assertSelectorIsEnabled('[data-checkout-button]');
            self::assertSelectorNotExists('[data-cart-unavailable-alert]');
            $checkoutButton = $client->getWebDriver()->findElement(
                WebDriverBy::cssSelector('[data-checkout-button]')
            );
            self::assertTrue($checkoutButton->isDisplayed());
            self::assertTrue($checkoutButton->isEnabled());
            self::assertCount(3, $client->getWebDriver()->findElements(WebDriverBy::cssSelector('[data-cart-product-id]')));

            $this->makeProductsUnavailable($context);
            $this->activateRenderedControl($client, '[data-checkout-button]');
            $client->waitFor('[data-cart-unavailable-alert]');

            self::assertSelectorNotExists($activeRow.'[data-unavailable-reason]');
            self::assertSelectorExists($unpublishedRow.'[data-unavailable-reason="unpublished"]');
            self::assertSelectorExists($deletedRow.'[data-unavailable-reason="deleted"]');
            self::assertSelectorTextContains(
                $unpublishedRow.' [data-cart-product-unavailable-message]',
                'Товар снят с продажи и недоступен для заказа.'
            );
            self::assertSelectorTextContains(
                $deletedRow.' [data-cart-product-unavailable-message]',
                'Товар удалён и больше не может быть заказан.'
            );
            self::assertSelectorTextContains(
                '[data-cart-unavailable-alert]',
                'В корзине есть недоступные товары. Удалите их перед оформлением заказа.'
            );
            self::assertSelectorIsDisabled('[data-checkout-button]');
            self::assertSelectorIsDisabled($unpublishedRow.' input[type="number"]');
            self::assertSelectorIsDisabled($deletedRow.' input[type="number"]');
            self::assertSelectorNotExists($unpublishedRow.' figure a');
            self::assertSelectorNotExists($unpublishedRow.' .product-title a');
            self::assertSelectorNotExists($deletedRow.' figure a');
            self::assertSelectorNotExists($deletedRow.' .product-title a');
            self::assertSelectorExists($unpublishedRow.' [data-remove-cart-product]');
            self::assertSelectorExists($deletedRow.' [data-remove-cart-product]');
            $this->assertCartExists($context['cartId']);
            self::assertSame($context['orderCount'], $this->getOrderCount());

            $this->activateRenderedControl($client, $unpublishedRow.' [data-remove-cart-product]');
            $this->waitForSelectorAbsence($client, $unpublishedRow);

            self::assertSelectorNotExists($unpublishedRow);
            self::assertSelectorExists($deletedRow.'[data-unavailable-reason="deleted"]');
            self::assertSelectorExists('[data-cart-unavailable-alert]');
            self::assertSelectorIsDisabled('[data-checkout-button]');

            $this->activateRenderedControl($client, $deletedRow.' [data-remove-cart-product]');
            $this->waitForSelectorAbsence($client, $deletedRow);
            $this->waitForSelectorAbsence($client, '[data-cart-unavailable-alert]');

            self::assertSelectorNotExists($deletedRow);
            self::assertSelectorNotExists('[data-cart-unavailable-alert]');
            self::assertSelectorIsEnabled('[data-checkout-button]');

            $this->activateRenderedControl($client, '[data-checkout-button]');
            $client->waitForElementToContain('.alert', 'Thank you for your purchase!');

            self::assertSame($context['orderCount'] + 1, $this->getOrderCount());
            $this->assertOrderContainsOnlyActiveProduct($context['activeProductId']);
            $this->assertCartWasConsumed($context['cartId']);
            $this->assertBrowserLogHasNoApplicationErrors($client);
        } catch (\Throwable $exception) {
            $testFailure = $exception;
        } finally {
            $cleanupFailure = null;

            try {
                $client->restart();
            } catch (\Throwable $exception) {
                $cleanupFailure = $exception;
            }

            if (null !== $context) {
                try {
                    $this->removeCartContext($context);
                } catch (\Throwable $exception) {
                    $cleanupFailure ??= $exception;
                }
            }

            if (null === $testFailure && null !== $cleanupFailure) {
                throw $cleanupFailure;
            }
        }

        if (null !== $testFailure) {
            throw $testFailure;
        }
    }

    /**
     * @return array{
     *     cartId: int,
     *     token: string,
     *     orderCount: int,
     *     activeProductId: int,
     *     unpublishedProductId: int,
     *     deletedProductId: int,
     *     activeCartProductId: int,
     *     unpublishedCartProductId: int,
     *     deletedCartProductId: int,
     *     imageIds: list<int>
     * }
     */
    private function createCartContext(): array
    {
        $entityManager = $this->getEntityManager();
        $suffix = str_replace('.', '', uniqid('', true));
        $cart = (new Cart())->setToken(TokenGenerator::generateToken());
        $products = [];
        $imageIds = [];

        foreach (['Active', 'Unpublished', 'Deleted'] as $name) {
            $product = (new Product())
                ->setTitle('Browser cart '.$name.' '.$suffix)
                ->setPrice('10.00')
                ->setQuantity(10)
                ->setIsPublished(true);
            $product->addProductImage(
                (new ProductImage())
                    ->setFilenameBig('browser-'.$suffix.'.jpg')
                    ->setFilenameMiddle('browser-'.$suffix.'.jpg')
                    ->setFilenameSmall('browser-'.$suffix.'.jpg')
            );
            $cart->addCartProduct(
                (new CartProduct())
                    ->setProduct($product)
                    ->setQuantity(1)
            );
            $entityManager->persist($product);
            $products[strtolower($name)] = $product;
        }

        $entityManager->persist($cart);
        $entityManager->flush();
        $orderCount = $entityManager->getRepository(Order::class)->count([]);

        $cartId = $cart->getId();
        self::assertNotNull($cartId);
        foreach ($products as $product) {
            self::assertNotNull($product->getId());
            $image = $product->getProductImages()->first();
            self::assertInstanceOf(ProductImage::class, $image);
            self::assertNotNull($image->getId());
            $imageIds[] = $image->getId();
        }

        $cartProductIds = [];
        foreach ($cart->getCartProducts() as $cartProduct) {
            $product = $cartProduct->getProduct();
            self::assertInstanceOf(Product::class, $product);
            self::assertNotNull($cartProduct->getId());
            $cartProductIds[$product->getId()] = $cartProduct->getId();
        }
        $this->commitCurrentTestTransactionForBrowser(false);

        return [
            'cartId' => $cartId,
            'token' => $cart->getToken(),
            'orderCount' => $orderCount,
            'activeProductId' => $products['active']->getId(),
            'unpublishedProductId' => $products['unpublished']->getId(),
            'deletedProductId' => $products['deleted']->getId(),
            'activeCartProductId' => $cartProductIds[$products['active']->getId()],
            'unpublishedCartProductId' => $cartProductIds[$products['unpublished']->getId()],
            'deletedCartProductId' => $cartProductIds[$products['deleted']->getId()],
            'imageIds' => $imageIds,
        ];
    }

    private function logInAndOpenCart(Client $client, string $token): void
    {
        $client->request('GET', '/ru/login');
        $client->getWebDriver()
            ->findElement(WebDriverBy::cssSelector('#inputEmail'))
            ->sendKeys(UserFixtures::USER_1_EMAIL);
        $client->getWebDriver()
            ->findElement(WebDriverBy::cssSelector('#inputPassword'))
            ->sendKeys('test3test3');
        $this->activateRenderedControl($client, '#form button[type="submit"]');
        $client->waitFor('#page_header_title');
        self::assertSame(self::$baseUri.'/ru/profile', $client->getCurrentURL());
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $token));
        $client->request('GET', '/ru/cart');
    }

    /** @param array<string, mixed> $context */
    private function makeProductsUnavailable(array $context): void
    {
        $entityManager = $this->getEntityManager();
        $this->ensureTestTransaction($entityManager);
        $unpublishedProduct = $entityManager->find(Product::class, $context['unpublishedProductId']);
        $deletedProduct = $entityManager->find(Product::class, $context['deletedProductId']);
        self::assertInstanceOf(Product::class, $unpublishedProduct);
        self::assertInstanceOf(Product::class, $deletedProduct);

        $unpublishedProduct->setIsPublished(false);
        $deletedProduct->setIsDeleted(true);
        $entityManager->flush();
        $this->commitCurrentTestTransactionForBrowser(false);
    }

    private function activateRenderedControl(Client $client, string $selector): void
    {
        $driver = $client->getWebDriver();
        $by = WebDriverBy::cssSelector($selector);
        $element = (new WebDriverWait($driver, 30))->until(
            WebDriverExpectedCondition::presenceOfElementLocated($by)
        );
        self::assertTrue($element->isDisplayed());
        self::assertTrue($element->isEnabled());
        $client->executeScript(
            'arguments[0].scrollIntoView({block: "center", inline: "nearest"});',
            [$element]
        );
        $client->executeScript('arguments[0].click();', [$element]);
    }

    private function waitForSelectorAbsence(Client $client, string $selector): void
    {
        $driver = $client->getWebDriver();
        $by = WebDriverBy::cssSelector($selector);

        (new WebDriverWait($driver, 30))->until(
            static fn (): bool => [] === $driver->findElements($by)
        );
    }

    private function cartProductSelector(int $cartProductId): string
    {
        return '[data-cart-product-id="'.$cartProductId.'"]';
    }

    private function assertBrowserLogHasNoApplicationErrors(Client $client): void
    {
        foreach ($client->getWebDriver()->manage()->getLog('browser') as $entry) {
            $message = (string) ($entry['message'] ?? '');
            self::assertDoesNotMatchRegularExpression(
                '/Uncaught|Unhandled|TypeError|status (?:code |of )?5\\d\\d/i',
                $message
            );
        }
    }

    private function assertCartExists(int $cartId): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        self::assertInstanceOf(Cart::class, $entityManager->find(Cart::class, $cartId));
    }

    private function assertCartWasConsumed(int $cartId): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        self::assertNull($entityManager->find(Cart::class, $cartId));
    }

    private function getOrderCount(): int
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        return $entityManager->getRepository(Order::class)->count([]);
    }

    private function assertOrderContainsOnlyActiveProduct(int $activeProductId): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        $order = $entityManager->getRepository(Order::class)->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(Order::class, $order);
        self::assertCount(1, $order->getOrderProducts());
        $orderProduct = $order->getOrderProducts()->first();
        self::assertSame($activeProductId, $orderProduct->getProduct()?->getId());
    }

    /** @param array<string, mixed> $context */
    private function removeCartContext(array $context): void
    {
        $entityManager = $this->getEntityManager();
        $this->ensureTestTransaction($entityManager);
        $entityManager->clear();
        $activeProduct = $entityManager->find(Product::class, $context['activeProductId']);
        if ($activeProduct instanceof Product) {
            foreach ($activeProduct->getOrderProducts() as $orderProduct) {
                $order = $orderProduct->getAppOrder();
                if ($order instanceof Order) {
                    $entityManager->remove($order);
                }
            }
        }
        $cart = $entityManager->find(Cart::class, $context['cartId']);
        if ($cart instanceof Cart) {
            $entityManager->remove($cart);
        }
        $entityManager->flush();
        $entityManager->clear();
        foreach ($context['imageIds'] as $imageId) {
            $image = $entityManager->find(ProductImage::class, $imageId);
            if ($image instanceof ProductImage) {
                $entityManager->remove($image);
            }
        }
        foreach (['activeProductId', 'unpublishedProductId', 'deletedProductId'] as $productIdKey) {
            $product = $entityManager->find(Product::class, $context[$productIdKey]);
            if ($product instanceof Product) {
                $entityManager->remove($product);
            }
        }
        $entityManager->flush();
        $this->commitCurrentTestTransactionForBrowser();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function ensureTestTransaction(EntityManagerInterface $entityManager): void
    {
        if (!$entityManager->getConnection()->isTransactionActive()) {
            StaticDriver::beginTransaction();
        }
    }

    private function commitCurrentTestTransactionForBrowser(bool $beginNewTransaction = true): void
    {
        StaticDriver::commit();
        if ($beginNewTransaction) {
            StaticDriver::beginTransaction();
        }
    }
}
