<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\SymfonyPanther\BasePantherTestCase;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use App\Utils\Money\DecimalMoney;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverWait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\Client;

class OrderEditorBrowserTest extends BasePantherTestCase
{
    #[Group(name: 'functional-panther')]
    public function testAdminOrderEditorMutatesControlledLinesWithPanther(): void
    {
        $client = static::createPantherClient(['browser' => self::CHROME]);

        $this->assertAdminOrderEditorMutatesLines($client);
    }

    #[Group(name: 'functional-selenium')]
    public function testAdminOrderEditorMutatesControlledLinesWithSelenium(): void
    {
        $client = $this->initSeleniumClient();

        $this->assertAdminOrderEditorMutatesLines($client);
    }

    private function assertAdminOrderEditorMutatesLines(Client $client): void
    {
        $context = null;
        $testFailure = null;
        try {
            $context = $this->createControlledOrder();

            $client->request('GET', '/ru/admin/login');
            $client->submitForm('Войти', [
                'email' => 'test2@test.com',
                'password' => 'test2test2',
            ]);
            $editorUri = '/ru/admin/order/edit/'.$context['orderId'];
            $client->request('GET', $editorUri);
            $crawler = $client->waitForElementToContain('body', $context['expectedLines'][1]['productTitle']);

            $app = $crawler->filter('.table-additional-selection');
            self::assertCount(1, $app);
            $rows = $app->filterXPath('./div[contains(concat(" ", normalize-space(@class), " "), " row ") and contains(concat(" ", normalize-space(@class), " "), " mb-1 ")]');
            self::assertCount(3, $rows);

            $rowTexts = [$rows->eq(0)->text(), $rows->eq(1)->text()];
            foreach ($context['expectedLines'] as $expectedLine) {
                $rowText = $this->findProductRowText($rowTexts, $expectedLine['productTitle']);
                self::assertStringContainsString((string) $expectedLine['quantity'], $rowText);
                self::assertSame($expectedLine['pricePerOneCents'], $this->productPriceTextToCents($rowText));
            }

            $computedTotal = $rows->eq(2)->filter('span.font-weight-bold.ml-2');
            self::assertCount(1, $computedTotal);
            self::assertSame('$274.88', trim($computedTotal->text()));
            $this->assertStoredTotal($crawler, 27488);

            $linesAfterAdd = $this->dispatchOrderProductAction($client, 'add', [
                'categoryId' => $context['addedCategoryId'],
                'productId' => $context['addedProductUuid'],
                'quantity' => 3,
                'pricePerOne' => '0.01',
            ]);
            $addedLines = array_values(array_filter(
                $linesAfterAdd,
                static fn (array $line): bool => $context['addedProductId'] === ($line['product']['id'] ?? null)
            ));
            self::assertCount(1, $addedLines);
            self::assertSame('12.34', $addedLines[0]['pricePerOne']);
            self::assertSame(3, $addedLines[0]['quantity']);
            self::assertIsInt($addedLines[0]['id']);

            $client->waitForElementToContain('body', $context['addedProductTitle']);
            $client->waitForElementToContain('body', '$311.9');
            $client->request('GET', $editorUri);
            $crawler = $client->waitForElementToContain('body', $context['addedProductTitle']);
            $this->assertStoredTotal($crawler, 31190);

            $linesAfterDelete = $this->dispatchOrderProductAction($client, 'remove', $addedLines[0]['id']);
            self::assertCount(2, $linesAfterDelete);
            self::assertNotContains($context['addedProductId'], array_column(array_column($linesAfterDelete, 'product'), 'id'));
            (new WebDriverWait($client->getWebDriver(), 30))->until(
                fn (): bool => !str_contains($client->getPageSource(), $context['addedProductTitle'])
            );
            $client->waitForElementToContain('body', '$274.88');

            $client->request('GET', $editorUri);
            $crawler = $client->waitForElementToContain('body', $context['expectedLines'][1]['productTitle']);
            $this->assertStoredTotal($crawler, 27488);

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
                    $this->removeControlledOrder($context);
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
     *     orderId: int,
     *     expectedLines: list<array{productTitle: string, quantity: int, pricePerOneCents: int}>,
     *     addedCategoryId: int,
     *     addedProductId: int,
     *     addedProductUuid: string,
     *     addedProductTitle: string,
     *     productIds: list<int>,
     *     categoryIds: list<int>
     * }
     */
    private function createControlledOrder(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $firstCategory = (new Category())->setTitle('Browser boots '.$suffix);
        $secondCategory = (new Category())->setTitle('Browser socks '.$suffix);
        $addedCategory = (new Category())->setTitle('Browser sandals '.$suffix);
        $firstProduct = (new Product())
            ->setTitle('Browser Trail Boot '.$suffix)
            ->setPrice('89.99')
            ->setQuantity(100)
            ->setCategory($firstCategory);
        $secondProduct = (new Product())
            ->setTitle('Browser Merino Sock '.$suffix)
            ->setPrice('94.90')
            ->setQuantity(50)
            ->setCategory($secondCategory);
        $addedProduct = (new Product())
            ->setTitle('Browser Summer Sandal '.$suffix)
            ->setPrice('12.34')
            ->setQuantity(25)
            ->setCategory($addedCategory);
        $firstLine = (new OrderProduct())
            ->setProduct($firstProduct)
            ->setQuantity(2)
            ->setPricePerOne('89.99');
        $secondLine = (new OrderProduct())
            ->setProduct($secondProduct)
            ->setQuantity(1)
            ->setPricePerOne('94.90');
        $owner = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => UserFixtures::USER_ADMIN_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $owner);

        $order = (new Order())
            ->setOwner($owner)
            ->setStatus(1)
            ->setTotalPrice('274.88');
        $order->addOrderProduct($firstLine);
        $order->addOrderProduct($secondLine);

        foreach ([$firstCategory, $secondCategory, $addedCategory, $firstProduct, $secondProduct, $addedProduct, $order] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $this->commitCurrentTestTransactionForBrowser(false);

        foreach ([$firstLine, $secondLine, $firstProduct, $secondProduct, $addedProduct, $firstCategory, $secondCategory, $addedCategory] as $entity) {
            self::assertNotNull($entity->getId());
        }

        $orderId = $order->getId();
        $firstProductTitle = $firstProduct->getTitle();
        $secondProductTitle = $secondProduct->getTitle();
        $addedProductTitle = $addedProduct->getTitle();
        self::assertNotNull($orderId);
        self::assertNotNull($firstProductTitle);
        self::assertNotNull($secondProductTitle);
        self::assertNotNull($addedProductTitle);

        return [
            'orderId' => $orderId,
            'expectedLines' => [
                ['productTitle' => $firstProductTitle, 'quantity' => 2, 'pricePerOneCents' => 8999],
                ['productTitle' => $secondProductTitle, 'quantity' => 1, 'pricePerOneCents' => 9490],
            ],
            'addedCategoryId' => $addedCategory->getId(),
            'addedProductId' => $addedProduct->getId(),
            'addedProductUuid' => (string) $addedProduct->getUuid(),
            'addedProductTitle' => $addedProductTitle,
            'productIds' => [$firstProduct->getId(), $secondProduct->getId(), $addedProduct->getId()],
            'categoryIds' => [$firstCategory->getId(), $secondCategory->getId(), $addedCategory->getId()],
        ];
    }

    /** @param list<string> $rowTexts */
    private function findProductRowText(array $rowTexts, string $productTitle): string
    {
        foreach ($rowTexts as $rowText) {
            if (str_contains($rowText, $productTitle)) {
                return $rowText;
            }
        }

        self::fail(sprintf('Product row for "%s" was not found.', $productTitle));
    }

    /**
     * @param array{
     *     orderId: int,
     *     productIds: list<int>,
     *     categoryIds: list<int>
     * } $context
     */
    private function removeControlledOrder(array $context): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager->getConnection()->isTransactionActive()) {
            StaticDriver::beginTransaction();
        }
        $entityManager->clear();
        $order = $entityManager->find(Order::class, $context['orderId']);
        if ($order instanceof Order) {
            $entityManager->remove($order);
        }
        $entityManager->flush();
        foreach ($context['productIds'] as $productId) {
            $product = $entityManager->find(Product::class, $productId);
            if ($product instanceof Product) {
                $entityManager->remove($product);
            }
        }
        foreach ($context['categoryIds'] as $categoryId) {
            $category = $entityManager->find(Category::class, $categoryId);
            if ($category instanceof Category) {
                $entityManager->remove($category);
            }
        }
        $entityManager->flush();
        $this->commitCurrentTestTransactionForBrowser();
    }

    private function commitCurrentTestTransactionForBrowser(bool $beginNewTransaction = true): void
    {
        StaticDriver::commit();
        if ($beginNewTransaction) {
            StaticDriver::beginTransaction();
        }
    }

    /** @return list<array<string, mixed>> */
    private function dispatchOrderProductAction(Client $client, string $action, mixed $payload): array
    {
        $result = $client->getWebDriver()->executeAsyncScript(
            <<<'JS'
            const action = arguments[0];
            const payload = arguments[1];
            const done = arguments[arguments.length - 1];
            const storeOwner = Array.from(document.querySelectorAll("*")).find(
                (element) => element.__vue__ && element.__vue__.$store
            );

            if (!storeOwner || !storeOwner.__vue__ || !storeOwner.__vue__.$store) {
                done({ status: "error", message: "Order editor Vuex store was not found." });
                return;
            }

            const store = storeOwner.__vue__.$store;
            let command;
            if ("add" === action) {
                store.commit("products/setNewProductInfo", payload);
                command = store.dispatch("products/addNewOrderProduct");
            } else if ("remove" === action) {
                command = store.dispatch("products/removeOrderProduct", payload);
            } else {
                done({ status: "error", message: "Unknown order editor action." });
                return;
            }

            Promise.resolve(command)
                .then(() => store.dispatch("products/getOrderProducts"))
                .then(
                    () => done({
                        status: "ok",
                        lines: store.state.products.orderProducts,
                    }),
                    (error) => done({
                        status: "error",
                        message: error && error.message ? error.message : "Order editor action failed.",
                    })
                );
            JS,
            [$action, $payload]
        );

        self::assertIsArray($result);
        self::assertSame('ok', $result['status'] ?? null, (string) ($result['message'] ?? ''));
        self::assertIsArray($result['lines'] ?? null);

        return $result['lines'];
    }

    private function assertStoredTotal(\Symfony\Component\DomCrawler\Crawler $crawler, int $expectedCents): void
    {
        $storedTotalRow = $crawler->filterXPath('//div[contains(concat(" ", normalize-space(@class), " "), " form-group ") and contains(concat(" ", normalize-space(@class), " "), " row ")][div[1][contains(normalize-space(), "Общая стоимость")]]');
        self::assertCount(1, $storedTotalRow);
        self::assertSame($expectedCents, $this->currencyTextToCents($storedTotalRow->filter('div')->eq(1)->text()));
    }

    private function currencyTextToCents(string $currencyText): int
    {
        $currencyText = str_replace(["\u{00A0}", "\u{202F}"], '', $currencyText);
        self::assertMatchesRegularExpression('/(?<amount>\d+(?:[.,]\d{1,2})?)/', $currencyText);
        preg_match('/(?<amount>\d+(?:[.,]\d{1,2})?)/', $currencyText, $matches);

        return $this->decimalAmountToCents($matches['amount']);
    }

    private function productPriceTextToCents(string $rowText): int
    {
        self::assertMatchesRegularExpression('/\\$(?<amount>\d+(?:\.\d{1,2})?)/', $rowText);
        preg_match('/\\$(?<amount>\d+(?:\.\d{1,2})?)/', $rowText, $matches);

        return $this->decimalAmountToCents($matches['amount']);
    }

    private function decimalAmountToCents(string $amount): int
    {
        $amount = str_replace(',', '.', $amount);
        if (!str_contains($amount, '.')) {
            $amount .= '.00';
        } elseif (strlen($amount) - strpos($amount, '.') === 2) {
            $amount .= '0';
        }

        return DecimalMoney::toCents($amount);
    }

    private function assertBrowserLogHasNoApplicationErrors(Client $client): void
    {
        foreach ($client->getWebDriver()->manage()->getLog('browser') as $entry) {
            $message = (string) ($entry['message'] ?? '');
            self::assertStringNotContainsString('Uncaught (in promise)', $message);
            self::assertStringNotContainsString('Request failed with status code 500', $message);
            self::assertStringNotContainsString('max_joins', $message);
            self::assertNotSame('SEVERE', strtoupper((string) ($entry['level'] ?? '')), $message);
        }
    }
}
