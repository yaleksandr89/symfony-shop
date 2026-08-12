<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use App\Utils\Money\DecimalMoney;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverWait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

class OrderEditorBrowserTest extends PantherTestCase
{
    #[Group(name: 'functional-panther')]
    #[TestDox('Администратор через интерфейс добавляет и удаляет позицию заказа в Panther')]
    public function testAdminOrderEditorMutatesControlledLinesWithPanther(): void
    {
        $context = null;
        $testFailure = null;
        try {
            static::stopWebServer();
            $context = $this->createControlledOrder();
            $client = static::createPantherClient(['browser' => self::CHROME]);

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
            $this->selectOptionByValue(
                $client,
                'select[name="add_product_category_select"]',
                (string) $context['addedCategoryId']
            );
            $this->selectOptionByValue(
                $client,
                'select[name="add_product_product_select"]',
                $context['addedProductUuid']
            );
            $inputSelector = '.table-additional-selection > .row.mb-2 input[type="number"]';
            $this->setVueBoundInputValue($client, $inputSelector, 0, '3');
            $this->setVueBoundInputValue($client, $inputSelector, 1, '12.34');
            $driver = $client->getWebDriver();
            $categorySelect = $driver->findElement(WebDriverBy::cssSelector('select[name="add_product_category_select"]'));
            $productSelect = $driver->findElement(WebDriverBy::cssSelector('select[name="add_product_product_select"]'));
            $inputs = $driver->findElements(
                WebDriverBy::cssSelector('.table-additional-selection > .row.mb-2 input[type="number"]')
            );
            $addButton = $driver->findElement(
                WebDriverBy::cssSelector('.table-additional-selection > .row.mb-2 .btn-outline-success')
            );
            self::assertSame((string) $context['addedCategoryId'], $categorySelect->getDomProperty('value'));
            self::assertSame($context['addedProductUuid'], $productSelect->getDomProperty('value'));
            self::assertSame('3', $inputs[0]->getDomProperty('value'));
            self::assertSame('12.34', $inputs[1]->getDomProperty('value'));
            self::assertTrue($addButton->isDisplayed());
            self::assertTrue($addButton->isEnabled());
            $this->activateRenderedControl(
                $client,
                '.table-additional-selection > .row.mb-2 .btn-outline-success'
            );

            $addedRow = $this->waitForOrderProductRow($client, $context['addedProductTitle']);
            self::assertStringContainsString('3', $addedRow->getText());
            self::assertSame(1234, $this->productPriceTextToCents($addedRow->getText()));
            $this->assertComputedTotal($client, 31190, '$311.9');

            $client->request('GET', $editorUri);
            $crawler = $client->waitForElementToContain('body', $context['addedProductTitle']);
            $addedRow = $this->waitForOrderProductRow($client, $context['addedProductTitle']);
            self::assertStringContainsString('3', $addedRow->getText());
            self::assertSame(1234, $this->productPriceTextToCents($addedRow->getText()));
            $this->assertComputedTotal($client, 31190, '$311.9');
            $this->assertStoredTotal($crawler, 31190);

            $this->activateRenderedElement(
                $client,
                $addedRow->findElement(WebDriverBy::cssSelector('.btn-outline-danger'))
            );
            $this->waitForOrderProductRowAbsence($client, $context['addedProductTitle']);
            $this->assertComputedTotal($client, 27488, '$274.88');

            $client->request('GET', $editorUri);
            $crawler = $client->waitForElementToContain('body', $context['expectedLines'][1]['productTitle']);
            self::assertNull($this->findOrderProductRow($client, $context['addedProductTitle']));
            $this->assertComputedTotal($client, 27488, '$274.88');
            $this->assertStoredTotal($crawler, 27488);

            $this->assertBrowserLogHasNoApplicationErrors($client);
        } catch (\Throwable $exception) {
            $testFailure = $exception;
        } finally {
            $cleanupFailure = null;
            try {
                static::stopWebServer();
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
            ->setIsPublished(true)
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

    private function selectOptionByValue(Client $client, string $selector, string $value): void
    {
        $driver = $client->getWebDriver();
        $by = WebDriverBy::cssSelector($selector);

        (new WebDriverWait($driver, 30))->until(
            static function () use ($driver, $by, $value): bool {
                $selects = $driver->findElements($by);
                if ([] === $selects) {
                    return false;
                }

                foreach ($selects[0]->findElements(WebDriverBy::tagName('option')) as $option) {
                    if ($value === $option->getDomProperty('value')) {
                        return true;
                    }
                }

                return false;
            }
        );

        (new WebDriverSelect($driver->findElement($by)))->selectByValue($value);
        (new WebDriverWait($driver, 30))->until(
            static fn (): bool => $value === $driver->findElement($by)->getDomProperty('value')
        );
    }

    private function setVueBoundInputValue(
        Client $client,
        string $selector,
        int $index,
        string $value
    ): void {
        $driver = $client->getWebDriver();
        $by = WebDriverBy::cssSelector($selector);

        (new WebDriverWait($driver, 30))->until(
            static fn (): bool => count($driver->findElements($by)) > $index
        );

        $element = $driver->findElements($by)[$index];
        self::assertTrue($element->isDisplayed());
        self::assertTrue($element->isEnabled());
        self::assertSame('number', $element->getDomProperty('type'));

        // Manual smoke proves physical usability; automation stays at the rendered DOM → Vue boundary.
        $client->executeScript(
            <<<'JS'
            const element = arguments[0];
            const value = arguments[1];

            element.focus();
            element.value = value;
            element.dispatchEvent(new Event("input", { bubbles: true }));
            element.dispatchEvent(new Event("change", { bubbles: true }));
            element.blur();
            JS,
            [$element, $value]
        );

        (new WebDriverWait($driver, 30))->until(
            static fn (): bool => $value === $driver->findElements($by)[$index]->getDomProperty('value')
        );
    }

    private function activateRenderedControl(Client $client, string $selector): void
    {
        $driver = $client->getWebDriver();
        $by = WebDriverBy::cssSelector($selector);
        $element = (new WebDriverWait($driver, 30))->until(
            WebDriverExpectedCondition::presenceOfElementLocated($by)
        );

        $this->activateRenderedElement($client, $element);
    }

    private function activateRenderedElement(Client $client, WebDriverElement $element): void
    {
        self::assertTrue($element->isDisplayed());
        self::assertTrue($element->isEnabled());
        $client->executeScript(
            'arguments[0].scrollIntoView({block: "center", inline: "nearest"});',
            [$element]
        );
        $client->executeScript('arguments[0].click();', [$element]);
    }

    private function waitForOrderProductRow(Client $client, string $productTitle): WebDriverElement
    {
        $row = (new WebDriverWait($client->getWebDriver(), 30))->until(
            fn (): ?WebDriverElement => $this->findOrderProductRow($client, $productTitle)
        );
        self::assertInstanceOf(WebDriverElement::class, $row);

        return $row;
    }

    private function waitForOrderProductRowAbsence(Client $client, string $productTitle): void
    {
        (new WebDriverWait($client->getWebDriver(), 30))->until(
            fn (): bool => null === $this->findOrderProductRow($client, $productTitle)
        );
    }

    private function findOrderProductRow(Client $client, string $productTitle): ?WebDriverElement
    {
        $rows = $client->getWebDriver()->findElements(
            WebDriverBy::cssSelector('.table-additional-selection > .row.mb-1')
        );
        foreach ($rows as $row) {
            if (str_contains($row->getText(), $productTitle)) {
                return $row;
            }
        }

        return null;
    }

    private function assertComputedTotal(Client $client, int $expectedCents, string $visibleAmount): void
    {
        $selector = '.table-additional-selection span.font-weight-bold.ml-2';
        $client->waitForElementToContain($selector, $visibleAmount);
        $text = $client->getWebDriver()->findElement(WebDriverBy::cssSelector($selector))->getText();

        self::assertSame($expectedCents, $this->currencyTextToCents($text));
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
