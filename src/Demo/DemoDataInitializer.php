<?php

declare(strict_types=1);

namespace App\Demo;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoDataInitializer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private DemoAssetInstaller $assetInstaller,
        private KernelInterface $kernel
    ) {
    }

    /**
     * @return array{
     *     users: array{created: int, updated: int},
     *     categories: array{created: int, updated: int},
     *     products: array{created: int, updated: int},
     *     images: array{created: int, existing: int, files_copied: int},
     *     credentials: array<int, array{email: string, password: string}>
     * }
     */
    public function initialize(): array
    {
        $catalog = $this->loadCatalog();
        $this->assetInstaller->assertSourcesExist($catalog['products']);

        $userCounts = $this->initUsers($catalog['users']);
        [$categoryCounts, $categoriesBySlug] = $this->initCategories($catalog['categories']);
        [$productCounts, $productsBySlug] = $this->initProducts($catalog['products'], $categoriesBySlug);

        $this->entityManager->flush();

        $imageCounts = $this->initImages($catalog['products'], $productsBySlug);

        $this->entityManager->flush();

        return [
            'users' => $userCounts,
            'categories' => $categoryCounts,
            'products' => $productCounts,
            'images' => $imageCounts,
            'credentials' => array_map(
                static fn (array $userData): array => [
                    'email' => $userData['email'],
                    'password' => $userData['password'],
                ],
                $catalog['users']
            ),
        ];
    }

    /**
     * @return array{
     *     users: array<int, array{email: string, password: string, roles: array<int, string>, full_name: string, phone: string, address: string, zip_code: int}>,
     *     categories: array<int, array{slug: string, title: string}>,
     *     products: array<int, array{slug: string, category_slug: string, title: string, price: string, quantity: int, description: string, image_key: string}>
     * }
     */
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

    /**
     * @param array<int, array{email: string, password: string, roles: array<int, string>, full_name: string, phone: string, address: string, zip_code: int}> $users
     *
     * @return array{created: int, updated: int}
     */
    private function initUsers(array $users): array
    {
        $counts = ['created' => 0, 'updated' => 0];
        $userRepository = $this->entityManager->getRepository(User::class);

        foreach ($users as $userData) {
            $user = $userRepository->findOneBy(['email' => $userData['email']]);
            $isNew = false;

            if (!$user instanceof User) {
                $user = new User();
                $user->setEmail($userData['email']);
                $this->entityManager->persist($user);
                $isNew = true;
                ++$counts['created'];
            } else {
                ++$counts['updated'];
            }

            $user->setRoles($userData['roles']);
            $user->setIsVerified(true);
            $user->setIsDeleted(false);
            $user->setFullName($userData['full_name']);
            $user->setPhone($userData['phone']);
            $user->setAddress($userData['address']);
            $user->setZipCode($userData['zip_code']);
            $user->setGoogleId(null);
            $user->setYandexId(null);
            $user->setVkontakteId(null);
            $user->setGithubId(null);

            if ($isNew || !$this->passwordHasher->isPasswordValid($user, $userData['password'])) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $userData['password']));
            }
        }

        return $counts;
    }

    /**
     * @param array<int, array{slug: string, title: string}> $categories
     *
     * @return array{0: array{created: int, updated: int}, 1: array<string, Category>}
     */
    private function initCategories(array $categories): array
    {
        $counts = ['created' => 0, 'updated' => 0];
        $categoriesBySlug = [];
        $categoryRepository = $this->entityManager->getRepository(Category::class);

        foreach ($categories as $categoryData) {
            $category = $categoryRepository->findOneBy(['slug' => $categoryData['slug']]);

            if (!$category instanceof Category) {
                $category = new Category();
                $category->setSlug($categoryData['slug']);
                $this->entityManager->persist($category);
                ++$counts['created'];
            } else {
                ++$counts['updated'];
            }

            $category->setTitle($categoryData['title']);
            $category->setIsDeleted(false);
            $categoriesBySlug[$categoryData['slug']] = $category;
        }

        return [$counts, $categoriesBySlug];
    }

    /**
     * @param array<int, array{slug: string, category_slug: string, title: string, price: string, quantity: int, description: string, image_key: string}> $products
     * @param array<string, Category>                                                                                                                     $categoriesBySlug
     *
     * @return array{0: array{created: int, updated: int}, 1: array<string, Product>}
     */
    private function initProducts(array $products, array $categoriesBySlug): array
    {
        $counts = ['created' => 0, 'updated' => 0];
        $productsBySlug = [];
        $productRepository = $this->entityManager->getRepository(Product::class);

        foreach ($products as $productData) {
            $product = $productRepository->findOneBy(['slug' => $productData['slug']]);

            if (!$product instanceof Product) {
                $product = new Product();
                $product->setSlug($productData['slug']);
                $this->entityManager->persist($product);
                ++$counts['created'];
            } else {
                ++$counts['updated'];
            }

            $category = $categoriesBySlug[$productData['category_slug']] ?? null;
            if (!$category instanceof Category) {
                throw new \RuntimeException(sprintf('Demo category "%s" was not initialized.', $productData['category_slug']));
            }

            $product->setTitle($productData['title']);
            $product->setSlug($productData['slug']);
            $product->setPrice($productData['price']);
            $product->setQuantity($productData['quantity']);
            $product->setDescription($productData['description']);
            $product->setIsPublished(true);
            $product->setIsDeleted(false);
            $product->setCategory($category);

            $productsBySlug[$productData['slug']] = $product;
        }

        return [$counts, $productsBySlug];
    }

    /**
     * @param array<int, array{slug: string, image_key: string}> $products
     * @param array<string, Product>                             $productsBySlug
     *
     * @return array{created: int, existing: int, files_copied: int}
     */
    private function initImages(array $products, array $productsBySlug): array
    {
        $counts = ['created' => 0, 'existing' => 0, 'files_copied' => 0];

        foreach ($products as $productData) {
            $product = $productsBySlug[$productData['slug']] ?? null;
            if (!$product instanceof Product) {
                throw new \RuntimeException(sprintf('Demo product "%s" was not initialized.', $productData['slug']));
            }

            $result = $this->assetInstaller->install($product, $productData['image_key']);
            $counts['files_copied'] += $result['copied'];

            if ($result['created']) {
                ++$counts['created'];
            } else {
                ++$counts['existing'];
            }
        }

        return $counts;
    }
}
