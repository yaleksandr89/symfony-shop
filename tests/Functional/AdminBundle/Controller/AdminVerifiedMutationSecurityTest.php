<?php

declare(strict_types=1);

namespace App\Tests\Functional\AdminBundle\Controller;

use App\Entity\Category;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
final class AdminVerifiedMutationSecurityTest extends WebTestCase
{
    #[DataProvider('referers')]
    #[TestDox('Неверифицированный администратор перенаправляется локально без удаления категории: $case')]
    public function testUnverifiedAdminIsRedirectedLocallyWithoutMutation(string $case, ?string $referer): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('Verified guard '.$suffix)
            ->setSlug('verified-guard-'.$suffix);
        $admin = (new User())
            ->setEmail('unverified-guard-'.$suffix.'@example.test')
            ->setPassword('not-used')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsVerified(false);
        $entityManager->persist($category);
        $entityManager->persist($admin);
        $entityManager->flush();
        $categoryId = $category->getId();
        self::assertNotNull($categoryId);
        $client->loginUser($admin, 'website');

        $deletePath = $this->router()->generate('admin_category_delete', [
            '_locale' => 'ru',
            'id' => $categoryId,
        ]);
        $editPage = $client->request('GET', $this->router()->generate('admin_category_edit', [
            '_locale' => 'ru',
            'id' => $categoryId,
        ]));
        self::assertResponseIsSuccessful();
        $deleteForm = $editPage->filter(sprintf('form[action="%s"]', $deletePath));
        self::assertCount(1, $deleteForm);
        $csrfToken = $deleteForm->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($csrfToken);
        $server = null === $referer ? [] : ['HTTP_REFERER' => $referer];

        $client->request('POST', $deletePath, ['_token' => $csrfToken], [], $server);

        $dashboardUrl = $this->router()->generate('admin_dashboard_show', ['_locale' => 'ru']);
        self::assertResponseRedirects($dashboardUrl, Response::HTTP_FOUND);
        self::assertStringNotContainsString('attacker.example', (string) $client->getResponse()->headers->get('location'));
        $entityManager->clear();
        $persistedCategory = $entityManager->find(Category::class, $categoryId);
        self::assertInstanceOf(Category::class, $persistedCategory);
        self::assertFalse((bool) $persistedCategory->getIsDeleted());
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function referers(): iterable
    {
        yield 'external Referer' => ['с внешним Referer', 'https://attacker.example/anything'];
        yield 'missing Referer' => ['без Referer', null];
    }

    private function router(): RouterInterface
    {
        return self::getContainer()->get(RouterInterface::class);
    }
}
