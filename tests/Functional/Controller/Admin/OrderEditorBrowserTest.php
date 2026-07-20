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
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\Client;

class OrderEditorBrowserTest extends BasePantherTestCase
{
    #[Group(name: 'functional-panther')]
    public function testAdminOrderEditorLoadsControlledLinesWithPanther(): void
    {
        $client = static::createPantherClient(['browser' => self::CHROME]);

        $this->assertAdminOrderEditorLoadsLines($client);
    }

    #[Group(name: 'functional-selenium')]
    public function testAdminOrderEditorLoadsControlledLinesWithSelenium(): void
    {
        $client = $this->initSeleniumClient();

        $this->assertAdminOrderEditorLoadsLines($client);
    }

    private function assertAdminOrderEditorLoadsLines(Client $client): void
    {
        $order = null;
        $testFailure = null;
        try {
            [$order, $expectedLines] = $this->createControlledOrder();
            $orderId = $order->getId();
            self::assertNotNull($orderId);

            $client->request('GET', '/ru/admin/login');
            $client->submitForm('Войти', [
                'email' => 'test2@test.com',
                'password' => 'test2test2',
            ]);
            $client->request('GET', '/ru/admin/order/edit/'.$orderId);
            $crawler = $client->waitForElementToContain('body', $expectedLines[1]['productTitle']);

            $app = $crawler->filter('.table-additional-selection');
            self::assertCount(1, $app);
            $rows = $app->filterXPath('./div[contains(concat(" ", normalize-space(@class), " "), " row ") and contains(concat(" ", normalize-space(@class), " "), " mb-1 ")]');
            self::assertCount(3, $rows);

            $rowTexts = [$rows->eq(0)->text(), $rows->eq(1)->text()];
            foreach ($expectedLines as $expectedLine) {
                $rowText = $this->findProductRowText($rowTexts, $expectedLine['productTitle']);
                self::assertStringContainsString((string) $expectedLine['quantity'], $rowText);
                self::assertSame($expectedLine['pricePerOneCents'], $this->productPriceTextToCents($rowText));
            }

            $computedTotal = $rows->eq(2)->filter('span.font-weight-bold.ml-2');
            self::assertCount(1, $computedTotal);
            self::assertSame('$274.88', trim($computedTotal->text()));

            $storedTotalRow = $crawler->filterXPath('//div[contains(concat(" ", normalize-space(@class), " "), " form-group ") and contains(concat(" ", normalize-space(@class), " "), " row ")][div[1][contains(normalize-space(), "Общая стоимость")]]');
            self::assertCount(1, $storedTotalRow);
            self::assertSame(27488, $this->currencyTextToCents($storedTotalRow->filter('div')->eq(1)->text()));

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

            if (null !== $order) {
                try {
                    $this->removeControlledOrder($order);
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
     * @return array{Order, list<array{productTitle: string, quantity: int, pricePerOneCents: int}>}
     */
    private function createControlledOrder(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $firstCategory = (new Category())->setTitle('Browser boots '.$suffix);
        $secondCategory = (new Category())->setTitle('Browser socks '.$suffix);
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

        foreach ([$firstCategory, $secondCategory, $firstProduct, $secondProduct, $order] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $this->commitCurrentTestTransactionForBrowser();

        foreach ([$firstLine, $secondLine, $firstProduct, $secondProduct, $firstCategory, $secondCategory] as $entity) {
            self::assertNotNull($entity->getId());
        }

        $firstProductTitle = $firstProduct->getTitle();
        $secondProductTitle = $secondProduct->getTitle();
        self::assertNotNull($firstProductTitle);
        self::assertNotNull($secondProductTitle);

        return [$order, [
            ['productTitle' => $firstProductTitle, 'quantity' => 2, 'pricePerOneCents' => 8999],
            ['productTitle' => $secondProductTitle, 'quantity' => 1, 'pricePerOneCents' => 9490],
        ]];
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

    private function removeControlledOrder(Order $order): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $products = [];
        $categories = [];
        foreach ($order->getOrderProducts() as $line) {
            $product = $line->getProduct();
            self::assertNotNull($product);
            $category = $product->getCategory();
            self::assertNotNull($category);
            $products[] = $product;
            $categories[] = $category;
            $entityManager->remove($line);
        }
        $entityManager->flush();

        $entityManager->remove($order);
        foreach ($products as $product) {
            $entityManager->remove($product);
        }
        foreach ($categories as $category) {
            $entityManager->remove($category);
        }
        $entityManager->flush();
        $this->commitCurrentTestTransactionForBrowser();
    }

    private function commitCurrentTestTransactionForBrowser(): void
    {
        StaticDriver::commit();
        StaticDriver::beginTransaction();
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
