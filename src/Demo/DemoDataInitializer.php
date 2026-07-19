<?php

declare(strict_types=1);

namespace App\Demo;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoDataInitializer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private DemoAssetInstaller $assetInstaller,
        private KernelInterface $kernel,
    ) {
    }

    /** @return array<string, mixed> */
    public function initialize(): array
    {
        $catalog = $this->loadCatalog();
        $this->validateCatalog($catalog);
        $this->assetInstaller->assertSourcesExist($catalog['products']);

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            [$userCounts, $usersByEmail] = $this->initUsers($catalog['users']);
            [$categoryCounts, $categoriesBySlug] = $this->initCategories($catalog['categories']);
            [$productCounts, $productsBySlug] = $this->initProducts($catalog['products'], $categoriesBySlug);
            $this->entityManager->flush();

            $imageCounts = $this->initImages($catalog['products'], $productsBySlug);
            $this->entityManager->flush();

            $removedOrderProducts = $this->entityManager->getRepository(OrderProduct::class)->count([]);
            $existingOrders = $this->entityManager->getRepository(Order::class)->findAll();
            foreach ($existingOrders as $existingOrder) {
                $this->entityManager->remove($existingOrder);
            }
            $this->entityManager->flush();

            $createdOrderProducts = $this->initOrders($catalog['orders'], $usersByEmail, $productsBySlug);
            $this->entityManager->flush();
            $connection->commit();
            $this->assetInstaller->commit();

            return [
                'users' => $userCounts,
                'categories' => $categoryCounts,
                'products' => $productCounts,
                'images' => $imageCounts['records'],
                'image_files' => $imageCounts['files'],
                'orders' => ['removed' => count($existingOrders), 'created' => count($catalog['orders'])],
                'order_products' => ['removed' => $removedOrderProducts, 'created' => $createdOrderProducts],
                'credentials' => array_map(static fn (array $user): array => [
                    'email' => $user['email'],
                    'password' => $user['password'],
                ], $catalog['users']),
            ];
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->assetInstaller->rollback();

            throw $exception;
        }
    }

    /** @return array{users: array, categories: array, products: array, orders: array} */
    private function loadCatalog(): array
    {
        $catalogPath = sprintf('%s/fixtures/demo/catalog.php', $this->kernel->getProjectDir());
        if (!is_file($catalogPath)) {
            throw new \RuntimeException(sprintf('Missing demo catalog: %s', $catalogPath));
        }

        $catalog = require $catalogPath;
        if (!is_array($catalog)) {
            throw new \RuntimeException(sprintf('Demo catalog must return an array: %s', $catalogPath));
        }

        return $catalog;
    }

    /** @param array{users: array, categories: array, products: array, orders: array} $catalog */
    private function validateCatalog(array $catalog): void
    {
        foreach (['users', 'categories', 'products', 'orders'] as $section) {
            $this->assertCatalogSection($catalog, $section);
        }

        $this->assertUnique($catalog['users'], 'email', 'user email');
        if (3 !== count($catalog['users'])) {
            throw new \RuntimeException('Demo catalog must define exactly 3 users.');
        }

        $this->assertUnique($catalog['categories'], 'slug', 'category slug');
        if (6 !== count($catalog['categories'])) {
            throw new \RuntimeException('Demo catalog must define exactly 6 categories.');
        }
        $categorySlugs = array_column($catalog['categories'], 'slug');
        foreach ($categorySlugs as $slug) {
            if (!is_string($slug) || !str_starts_with($slug, 'demo-')) {
                throw new \RuntimeException('Demo category slugs must start with "demo-".');
            }
        }

        $this->assertUnique($catalog['products'], 'slug', 'product slug');
        if (24 !== count($catalog['products'])) {
            throw new \RuntimeException('Demo catalog must define exactly 24 products.');
        }
        $this->assertUnique($catalog['products'], 'title', 'product title');
        $productsByCategory = array_fill_keys($categorySlugs, 0);
        foreach ($catalog['products'] as $product) {
            if (!is_array($product) || !isset($product['category_slug'], $product['price'], $product['quantity'], $product['description'], $product['image_key'])) {
                throw new \RuntimeException('Every demo product must provide all required fields.');
            }
            if (!isset($productsByCategory[$product['category_slug']])) {
                throw new \RuntimeException(sprintf('Demo product category "%s" does not exist.', $product['category_slug']));
            }
            if (!is_string($product['price']) || !preg_match('/^\d+\.\d{2}$/', $product['price']) || (int) $product['quantity'] <= 0) {
                throw new \RuntimeException(sprintf('Demo product "%s" has an invalid price or quantity.', $product['slug']));
            }
            ++$productsByCategory[$product['category_slug']];
        }
        foreach ($productsByCategory as $categorySlug => $count) {
            if (4 !== $count) {
                throw new \RuntimeException(sprintf('Demo category "%s" must contain exactly 4 products.', $categorySlug));
            }
        }

        if (24 !== count($catalog['orders'])) {
            throw new \RuntimeException('Demo catalog must define exactly 24 orders.');
        }
        $productSlugs = array_flip(array_column($catalog['products'], 'slug'));
        $userEmails = array_flip(array_column($catalog['users'], 'email'));
        $supportedStatuses = array_keys(OrderStaticStorage::getOrderStatusChoices());
        foreach ($catalog['orders'] as $order) {
            if (!is_array($order) || !isset($order['owner_email'], $order['created_at'], $order['updated_at'], $order['status'], $order['lines']) || !is_array($order['lines']) || 2 !== count($order['lines'])) {
                throw new \RuntimeException('Every demo order must define an owner, timestamps, status, and exactly two lines.');
            }
            if (!isset($userEmails[$order['owner_email']]) || !in_array($order['status'], $supportedStatuses, true)) {
                throw new \RuntimeException('Demo order owner or status is unsupported.');
            }
            try {
                new DateTimeImmutable($order['created_at']);
                new DateTimeImmutable($order['updated_at']);
            } catch (\Throwable) {
                throw new \RuntimeException('Demo order timestamps must be valid date-time strings.');
            }
            $lineSlugs = [];
            foreach ($order['lines'] as $line) {
                if (!is_array($line) || !isset($line['product_slug'], $line['quantity']) || !isset($productSlugs[$line['product_slug']]) || (int) $line['quantity'] <= 0) {
                    throw new \RuntimeException('Demo order lines must use catalog products and positive quantities.');
                }
                $lineSlugs[] = $line['product_slug'];
            }
            if (count($lineSlugs) !== count(array_unique($lineSlugs))) {
                throw new \RuntimeException('A demo order cannot contain the same product twice.');
            }
        }
    }

    private function assertCatalogSection(array $catalog, string $section): void
    {
        if (!array_key_exists($section, $catalog) || !is_array($catalog[$section])) {
            throw new \RuntimeException(sprintf('Demo catalog section "%s" is required.', $section));
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function assertUnique(array $items, string $key, string $label): void
    {
        $values = array_column($items, $key);
        if (count($values) !== count(array_unique($values))) {
            throw new \RuntimeException(sprintf('Demo catalog contains duplicate %s values.', $label));
        }
    }

    /** @param array<int, array<string, mixed>> $users @return array{0: array{created: int, updated: int, existing: int}, 1: array<string, User>} */
    private function initUsers(array $users): array
    {
        $counts = $this->emptyCounts();
        $usersByEmail = [];
        $repository = $this->entityManager->getRepository(User::class);
        foreach ($users as $data) {
            $user = $repository->findOneBy(['email' => $data['email']]);
            $changed = false;
            if (!$user instanceof User) {
                $user = (new User())->setEmail($data['email']);
                $this->entityManager->persist($user);
                ++$counts['created'];
            } else {
                $changed = $this->userNeedsUpdate($user, $data);
                if ($changed) {
                    ++$counts['updated'];
                } else {
                    ++$counts['existing'];
                }
            }
            $user->setRoles($data['roles'])->setIsVerified(true)->setIsDeleted(false)->setFullName($data['full_name'])->setPhone($data['phone'])->setAddress($data['address'])->setZipCode($data['zip_code']);
            $user->setGoogleId(null);
            $user->setYandexId(null);
            $user->setVkontakteId(null);
            $user->setGithubId(null);
            if ($this->passwordNeedsUpdate($user, $data['password'])) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            }
            $usersByEmail[$data['email']] = $user;
        }

        return [$counts, $usersByEmail];
    }

    /** @param array<string, mixed> $data */
    private function userNeedsUpdate(User $user, array $data): bool
    {
        $actualRoles = $user->getRoles();
        $expectedRoles = array_unique(array_merge($data['roles'], ['ROLE_USER']));
        sort($actualRoles);
        sort($expectedRoles);

        return $actualRoles !== $expectedRoles
            || !$user->isVerified() || $user->getIsDeleted() || $user->getFullName() !== $data['full_name']
            || $user->getPhone() !== $data['phone'] || $user->getAddress() !== $data['address'] || $user->getZipCode() !== $data['zip_code']
            || null !== $user->getGoogleId() || null !== $user->getYandexId() || null !== $user->getVkontakteId() || null !== $user->getGithubId()
            || $this->passwordNeedsUpdate($user, $data['password']);
    }

    private function passwordNeedsUpdate(User $user, string $password): bool
    {
        $property = new \ReflectionProperty(User::class, 'password');

        return !$property->isInitialized($user) || !$this->passwordHasher->isPasswordValid($user, $password);
    }

    /** @param array<int, array<string, mixed>> $categories @return array{0: array{created: int, updated: int, existing: int}, 1: array<string, Category>} */
    private function initCategories(array $categories): array
    {
        $counts = $this->emptyCounts();
        $bySlug = [];
        $repository = $this->entityManager->getRepository(Category::class);
        foreach ($categories as $data) {
            $category = $repository->findOneBy(['slug' => $data['slug']]);
            if (!$category instanceof Category) {
                $category = (new Category())->setSlug($data['slug']);
                $this->entityManager->persist($category);
                ++$counts['created'];
            } elseif ($category->getTitle() !== ucfirst(strtolower($data['title'])) || $category->getIsDeleted()) {
                ++$counts['updated'];
            } else {
                ++$counts['existing'];
            }
            $category->setTitle($data['title'])->setIsDeleted(false);
            $bySlug[$data['slug']] = $category;
        }

        return [$counts, $bySlug];
    }

    /** @param array<int, array<string, mixed>> $products @param array<string, Category> $categoriesBySlug @return array{0: array{created: int, updated: int, existing: int}, 1: array<string, Product>} */
    private function initProducts(array $products, array $categoriesBySlug): array
    {
        $counts = $this->emptyCounts();
        $bySlug = [];
        $repository = $this->entityManager->getRepository(Product::class);
        foreach ($products as $data) {
            $product = $repository->findOneBy(['slug' => $data['slug']]);
            $category = $categoriesBySlug[$data['category_slug']];
            if (!$product instanceof Product) {
                $product = new Product();
                $this->entityManager->persist($product);
                ++$counts['created'];
            } elseif ($product->getTitle() !== $data['title'] || $product->getPrice() !== $data['price'] || $product->getQuantity() !== $data['quantity'] || $product->getDescription() !== $data['description'] || !$product->getIsPublished() || $product->getIsDeleted() || $product->getCategory() !== $category) {
                ++$counts['updated'];
            } else {
                ++$counts['existing'];
            }
            $product->setSlug($data['slug'])->setTitle($data['title'])->setPrice($data['price'])->setQuantity($data['quantity'])->setDescription($data['description'])->setIsPublished(true)->setIsDeleted(false)->setCategory($category);
            $bySlug[$data['slug']] = $product;
        }

        return [$counts, $bySlug];
    }

    /** @param array<int, array<string, mixed>> $products @param array<string, Product> $productsBySlug @return array{records: array{created: int, updated: int, existing: int}, files: array{copied: int, updated: int, existing: int}} */
    private function initImages(array $products, array $productsBySlug): array
    {
        $records = $this->emptyCounts();
        $files = ['copied' => 0, 'updated' => 0, 'existing' => 0];
        foreach ($products as $data) {
            $result = $this->assetInstaller->install($productsBySlug[$data['slug']], $data['image_key']);
            ++$records[$result['record']];
            foreach ($files as $key => $unused) {
                $files[$key] += $result['files'][$key];
            }
        }

        return ['records' => $records, 'files' => $files];
    }

    /** @param array<int, array<string, mixed>> $orders @param array<string, User> $usersByEmail @param array<string, Product> $productsBySlug */
    private function initOrders(array $orders, array $usersByEmail, array $productsBySlug): int
    {
        $createdLines = 0;
        foreach ($orders as $data) {
            $order = (new Order())->setOwner($usersByEmail[$data['owner_email']])->setCreatedAt(new DateTimeImmutable($data['created_at']))->setUpdatedAt(new DateTimeImmutable($data['updated_at']))->setStatus($data['status'])->setIsDeleted(false);
            $totalCents = 0;
            foreach ($data['lines'] as $lineData) {
                $product = $productsBySlug[$lineData['product_slug']];
                $price = (string) $product->getPrice();
                $order->addOrderProduct((new OrderProduct())->setProduct($product)->setQuantity($lineData['quantity'])->setPricePerOne($price));
                $totalCents += $this->priceToCents($price) * $lineData['quantity'];
                ++$createdLines;
            }
            $order->setTotalPrice($totalCents / 100);
            $this->entityManager->persist($order);
        }

        return $createdLines;
    }

    private function priceToCents(string $price): int
    {
        if (!preg_match('/^(\d+)\.(\d{2})$/', $price, $matches)) {
            throw new \RuntimeException(sprintf('Invalid decimal demo price: %s', $price));
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }

    /** @return array{created: int, updated: int, existing: int} */
    private function emptyCounts(): array
    {
        return ['created' => 0, 'updated' => 0, 'existing' => 0];
    }
}
