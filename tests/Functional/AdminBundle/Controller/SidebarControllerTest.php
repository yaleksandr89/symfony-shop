<?php

declare(strict_types=1);

namespace App\Tests\Functional\AdminBundle\Controller;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

#[Group(name: 'functional')]
class SidebarControllerTest extends WebTestCase
{
    #[DataProvider(methodName: 'provideRouteScenarios')]
    #[TestDox('Боковая панель отмечает текущий маршрут')]
    public function testSidebarReflectsCurrentRoute(string $route, string $activeSection, ?string $activeChild): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', $this->getUrlForRoute($route));

        self::assertResponseIsSuccessful();

        $sidebar = $crawler->filter('#accordionSidebar');
        self::assertCount(1, $sidebar);
        self::assertCount(1, $sidebar->filter('li.nav-item.active'));

        $dashboard = $sidebar->filter('a.nav-link[href$="/admin/dashboard"]');
        self::assertCount(1, $dashboard);
        self::assertSame('dashboard' === $activeSection, $this->hasClass($dashboard->ancestors()->filter('li.nav-item')->first(), 'active'));

        foreach ($this->getSectionSelectors() as $section => $selectors) {
            $isActive = $section === $activeSection;
            $trigger = $sidebar->filter($selectors['trigger']);
            $collapse = $sidebar->filter($selectors['collapse']);
            $parent = $trigger->ancestors()->filter('li.nav-item')->first();

            self::assertCount(1, $trigger);
            self::assertCount(1, $collapse);
            self::assertSame($isActive, $this->hasClass($parent, 'active'));
            self::assertSame(!$isActive, $this->hasClass($trigger, 'collapsed'));
            self::assertSame($isActive ? 'true' : 'false', $trigger->attr('aria-expanded'));
            self::assertSame($isActive, $this->hasClass($collapse, 'show'));

            $activeLinks = $collapse->filter('a.collapse-item.active');
            self::assertSame($isActive && null !== $activeChild ? 1 : 0, $activeLinks->count());

            if ($isActive && null !== $activeChild) {
                self::assertCount(1, $collapse->filter(sprintf('a[href$="%s"].active', $activeChild)));
            }
        }

        self::assertSame(
            null === $activeChild ? 0 : 1,
            $sidebar->filter('.collapse a.collapse-item.active')->count(),
        );
    }

    public static function provideRouteScenarios(): Generator
    {
        yield 'dashboard' => ['admin_dashboard_show', 'dashboard', null];
        yield 'orders list' => ['admin_order_list', 'orders', '/admin/order/list'];
        yield 'orders add' => ['admin_order_add', 'orders', '/admin/order/add'];
        yield 'orders edit' => ['admin_order_edit', 'orders', null];
        yield 'products list' => ['admin_product_list', 'products', '/admin/product/list'];
        yield 'products add' => ['admin_product_add', 'products', '/admin/product/add'];
        yield 'products edit' => ['admin_product_edit', 'products', null];
        yield 'products blank edit' => ['admin_product_edit_blank', 'products', null];
        yield 'categories list' => ['admin_category_list', 'categories', '/admin/category/list'];
        yield 'categories add' => ['admin_category_add', 'categories', '/admin/category/add'];
        yield 'categories edit' => ['admin_category_edit', 'categories', null];
    }

    #[DataProvider(methodName: 'provideUserRouteScenarios')]
    #[TestDox('Боковая панель супер-администратора отмечает текущий маршрут')]
    public function testSuperAdminUserSidebarReflectsCurrentRoute(string $route, ?string $activeChild): void
    {
        $client = $this->createUserClient(UserFixtures::USER_SUPER_ADMIN_1_EMAIL);
        $crawler = $client->request('GET', $this->getUrlForRoute($route));

        self::assertResponseIsSuccessful();
        $sidebar = $crawler->filter('#accordionSidebar');
        $trigger = $sidebar->filter('a[data-target="#collapseUsers"]');
        $collapse = $sidebar->filter('#collapseUsers');

        self::assertCount(1, $sidebar->filter('li.nav-item.active'));
        self::assertCount(1, $trigger);
        self::assertFalse($this->hasClass($trigger, 'collapsed'));
        self::assertSame('true', $trigger->attr('aria-expanded'));
        self::assertTrue($this->hasClass($trigger->ancestors()->filter('li.nav-item')->first(), 'active'));
        self::assertTrue($this->hasClass($collapse, 'show'));
        self::assertSame(null === $activeChild ? 0 : 1, $collapse->filter('a.collapse-item.active')->count());

        if (null !== $activeChild) {
            self::assertCount(1, $collapse->filter(sprintf('a[href$="%s"].active', $activeChild)));
        }

        self::assertSame(null === $activeChild ? 0 : 1, $sidebar->filter('.collapse a.collapse-item.active')->count());
    }

    public static function provideUserRouteScenarios(): Generator
    {
        yield 'users list' => ['admin_user_list', '/admin/user/list'];
        yield 'users add' => ['admin_user_add', '/admin/user/add'];
        yield 'users edit' => ['admin_user_edit', null];
    }

    #[TestDox('Обычный администратор не видит раздел пользователей')]
    public function testOrdinaryAdminDoesNotSeeUsersSection(): void
    {
        $crawler = $this->createAdminClient()->request('GET', '/ru/admin/dashboard');

        self::assertResponseIsSuccessful();
        $sidebar = $crawler->filter('#accordionSidebar');
        self::assertCount(0, $sidebar->filter('a[data-target="#collapseUsers"]'));
        self::assertCount(0, $sidebar->filter('#collapseUsers'));
        self::assertStringNotContainsString('Общее', $sidebar->text());
        self::assertStringNotContainsString('Пользователи', $sidebar->text());
    }

    private function getUrlForRoute(string $route): string
    {
        return match ($route) {
            'admin_dashboard_show' => '/ru/admin/dashboard',
            'admin_order_list' => '/ru/admin/order/list',
            'admin_order_add' => '/ru/admin/order/add',
            'admin_order_edit' => sprintf('/ru/admin/order/edit/%d', $this->getEditableOrder()->getId()),
            'admin_product_list' => '/ru/admin/product/list',
            'admin_product_add' => '/ru/admin/product/add',
            'admin_product_edit' => sprintf('/ru/admin/product/edit/%d', $this->getEditableProduct()->getId()),
            'admin_product_edit_blank' => '/ru/admin/product/edit',
            'admin_category_list' => '/ru/admin/category/list',
            'admin_category_add' => '/ru/admin/category/add',
            'admin_category_edit' => sprintf('/ru/admin/category/edit/%d', $this->getEditableCategory()->getId()),
            'admin_user_list' => '/ru/admin/user/list',
            'admin_user_add' => '/ru/admin/user/add',
            'admin_user_edit' => sprintf('/ru/admin/user/edit/%d', $this->getEditableUser()->getId()),
        };
    }

    private function getSectionSelectors(): array
    {
        return [
            'orders' => ['trigger' => 'a[data-target="#collapseOrders"]', 'collapse' => '#collapseOrders'],
            'products' => ['trigger' => 'a[data-target="#collapseProducts"]', 'collapse' => '#collapseProducts'],
            'categories' => ['trigger' => 'a[data-target="#collapseCategories"]', 'collapse' => '#collapseCategories'],
        ];
    }

    private function hasClass(Crawler $crawler, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', $crawler->attr('class') ?? ''), true);
    }

    private function getEditableOrder(): Order
    {
        $order = self::getContainer()->get(OrderRepository::class)->findOneBy(['isDeleted' => false]);
        self::assertInstanceOf(Order::class, $order);

        return $order;
    }

    private function getEditableProduct(): Product
    {
        $product = self::getContainer()->get(ProductRepository::class)->findOneBy(['isDeleted' => false]);
        self::assertInstanceOf(Product::class, $product);

        return $product;
    }

    private function getEditableCategory(): Category
    {
        $category = self::getContainer()->get(CategoryRepository::class)->findOneBy(['isDeleted' => false]);
        self::assertInstanceOf(Category::class, $category);

        return $category;
    }

    private function getEditableUser(): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => UserFixtures::USER_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createAdminClient(): KernelBrowser
    {
        return $this->createUserClient(UserFixtures::USER_ADMIN_1_EMAIL);
    }

    private function createUserClient(string $email): KernelBrowser
    {
        $client = static::createClient();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => $email,
        ]);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user, 'website');

        return $client;
    }
}
