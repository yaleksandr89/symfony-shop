<?php

declare(strict_types=1);

namespace App\Tests\Functional\AdminBundle\Controller;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
final class AdminDestructiveActionSecurityTest extends WebTestCase
{
    #[DataProvider('resourcesAndRejectedRequests')]
    #[TestDox('Удаление $resource отклоняет GET и POST без валидного CSRF без мутации')]
    public function testRejectedRequestsDoNotMutateResource(
        string $resource,
        string $method,
        ?string $token,
    ): void {
        $client = static::createClient();
        $target = $this->createTarget($resource);
        $this->loginAuthorizedActor($client, $resource);
        $parameters = null === $token ? [] : ['_token' => $token];

        $client->request($method, $target['deletePath'], $parameters);

        if ('GET' === $method) {
            self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        } else {
            self::assertResponseRedirects('/ru/admin/login', Response::HTTP_FOUND);
        }
        $this->assertTargetMutation($resource, $target['id'], false);
    }

    #[DataProvider('resources')]
    #[TestDox('Реальная POST-форма с entity-specific CSRF удаляет $resource для нужной роли')]
    public function testRenderedPostFormWithValidCsrfMutatesResource(string $resource): void
    {
        $client = static::createClient();
        $target = $this->createTarget($resource);
        $this->loginAuthorizedActor($client, $resource);

        $crawler = $client->request('GET', $target['editPath']);
        self::assertResponseIsSuccessful();
        $deleteForm = $crawler->filter(sprintf('form[action="%s"]', $target['deletePath']));
        self::assertCount(1, $deleteForm);
        self::assertSame('post', strtolower((string) $deleteForm->attr('method')));
        self::assertCount(1, $deleteForm->filter('input[name="_token"]'));

        $client->submit($deleteForm->form());

        self::assertResponseRedirects($target['redirectPath'], Response::HTTP_FOUND);
        $this->assertTargetMutation($resource, $target['id'], true);
    }

    /** @return iterable<string, array{string}> */
    public static function resources(): iterable
    {
        yield 'category' => ['category'];
        yield 'product' => ['product'];
        yield 'product image' => ['product_image'];
        yield 'order' => ['order'];
        yield 'user' => ['user'];
    }

    /** @return iterable<string, array{string, string, string|null}> */
    public static function resourcesAndRejectedRequests(): iterable
    {
        foreach (array_keys(iterator_to_array(self::resources())) as $resource) {
            $resource = str_replace(' ', '_', $resource);
            yield $resource.' GET' => [$resource, 'GET', null];
            yield $resource.' missing CSRF' => [$resource, 'POST', null];
            yield $resource.' invalid CSRF' => [$resource, 'POST', 'invalid-token'];
        }
    }

    /**
     * @return array{id: int, deletePath: string, editPath: string, redirectPath: string}
     */
    private function createTarget(string $resource): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));

        if ('category' === $resource) {
            $entity = (new Category())
                ->setTitle('CSRF category '.$suffix)
                ->setSlug('csrf-category-'.$suffix);
            $entityManager->persist($entity);
            $entityManager->flush();

            return $this->targetPaths($resource, (int) $entity->getId());
        }

        if ('product' === $resource) {
            $category = self::getContainer()->get(CategoryRepository::class)->findOneBy(['isDeleted' => false]);
            self::assertInstanceOf(Category::class, $category);
            $entity = (new Product())
                ->setTitle('CSRF product '.$suffix)
                ->setSlug('csrf-product-'.$suffix)
                ->setPrice('10.00')
                ->setQuantity(1)
                ->setDescription('CSRF product description')
                ->setCategory($category);
            $entityManager->persist($entity);
            $entityManager->flush();

            return $this->targetPaths($resource, (int) $entity->getId());
        }

        if ('product_image' === $resource) {
            $category = self::getContainer()->get(CategoryRepository::class)->findOneBy(['isDeleted' => false]);
            self::assertInstanceOf(Category::class, $category);
            $product = (new Product())
                ->setTitle('CSRF image product '.$suffix)
                ->setSlug('csrf-image-product-'.$suffix)
                ->setPrice('10.00')
                ->setQuantity(1)
                ->setDescription('CSRF image product description')
                ->setCategory($category);
            $image = (new ProductImage())
                ->setFilenameBig('csrf-'.$suffix.'-big.jpg')
                ->setFilenameMiddle('csrf-'.$suffix.'-middle.jpg')
                ->setFilenameSmall('csrf-'.$suffix.'-small.jpg');
            $product->addProductImage($image);
            $entityManager->persist($product);
            $entityManager->flush();

            return [
                'id' => (int) $image->getId(),
                'deletePath' => sprintf('/ru/admin/product-image/delete/%d', $image->getId()),
                'editPath' => sprintf('/ru/admin/product/edit/%d', $product->getId()),
                'redirectPath' => sprintf('/ru/admin/product/edit/%d', $product->getId()),
            ];
        }

        if ('order' === $resource) {
            $owner = $this->fixtureUser(UserFixtures::USER_1_EMAIL);
            $entity = (new Order())
                ->setOwner($owner)
                ->setStatus(0)
                ->setTotalPrice('0.00');
            $entityManager->persist($entity);
            $entityManager->flush();

            return $this->targetPaths($resource, (int) $entity->getId());
        }

        $entity = (new User())
            ->setEmail('csrf-target-'.$suffix.'@example.test')
            ->setPassword('not-used')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true)
            ->setFullName('CSRF Target '.$suffix)
            ->setPhone(null)
            ->setAddress(null)
            ->setZipCode(null);
        $entityManager->persist($entity);
        $entityManager->flush();

        return $this->targetPaths($resource, (int) $entity->getId());
    }

    /** @return array{id: int, deletePath: string, editPath: string, redirectPath: string} */
    private function targetPaths(string $resource, int $id): array
    {
        return [
            'id' => $id,
            'deletePath' => sprintf('/ru/admin/%s/delete/%d', $resource, $id),
            'editPath' => sprintf('/ru/admin/%s/edit/%d', $resource, $id),
            'redirectPath' => sprintf('/ru/admin/%s/list', $resource),
        ];
    }

    private function loginAuthorizedActor(KernelBrowser $client, string $resource): void
    {
        $email = 'user' === $resource
            ? UserFixtures::USER_SUPER_ADMIN_1_EMAIL
            : UserFixtures::USER_ADMIN_1_EMAIL;
        $client->loginUser($this->fixtureUser($email), 'website');
    }

    private function fixtureUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function assertTargetMutation(string $resource, int $id, bool $expected): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        if ('product_image' === $resource) {
            self::assertSame($expected, null === $entityManager->find(ProductImage::class, $id));

            return;
        }

        $class = match ($resource) {
            'category' => Category::class,
            'product' => Product::class,
            'order' => Order::class,
            'user' => User::class,
        };
        $entity = $entityManager->find($class, $id);
        self::assertNotNull($entity);
        self::assertSame($expected, (bool) $entity->getIsDeleted());
    }
}
