<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group(name: 'functional')]
class AdminFlashControllerTest extends WebTestCase
{
    #[DataProvider(methodName: 'provideLocales')]
    public function testInvalidFormFlashIsLocalized(string $locale, array $messages): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', sprintf('/%s/admin/category/add', $locale));
        $crawler = $client->submit($crawler->filter('form[name="edit_category_form"]')->form([
            'edit_category_form[title]' => '',
        ]));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($messages['invalid'], $crawler->filter('.alert-warning')->text());
        self::assertStringNotContainsString($messages['oppositeInvalid'], $crawler->filter('.alert-warning')->text());
    }

    #[DataProvider(methodName: 'provideLocales')]
    public function testSaveAndDynamicDeleteFlashesAreLocalized(string $locale, array $messages): void
    {
        $client = $this->createAdminClient();
        $title = sprintf('Flash %s %s', $locale, str_replace('.', '', uniqid('', true)));
        $crawler = $client->request('GET', sprintf('/%s/admin/category/add', $locale));
        $client->submit($crawler->filter('form[name="edit_category_form"]')->form([
            'edit_category_form[title]' => $title,
        ]));
        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        self::assertStringContainsString($messages['saved'], $crawler->filter('.alert-success')->text());

        $category = self::getContainer()->get(CategoryRepository::class)->findOneBy(['title' => ucfirst(strtolower($title))]);
        self::assertInstanceOf(Category::class, $category);
        $deletePath = sprintf('/%s/admin/category/delete/%d', $locale, $category->getId());
        $deleteForm = $crawler->filter(sprintf('form[action="%s"]', $deletePath));
        self::assertCount(1, $deleteForm);
        $client->submit($deleteForm->form());
        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        $flash = $crawler->filter('.alert-warning')->text();
        self::assertStringContainsString($messages['deleted'], $flash);
        self::assertStringContainsString((string) $category->getId(), $flash);
        self::assertStringContainsString((string) $category->getTitle(), $flash);
    }

    #[DataProvider(methodName: 'provideLocales')]
    #[TestDox('Отказ в доступе локализуется через реальный HTTP-поток')]
    public function testAccessDeniedFlashIsLocalizedThroughHttpFlow(string $locale, array $messages): void
    {
        $client = static::createClient();
        $user = (new User())
            ->setEmail(sprintf('unverified-%s-%s@example.test', $locale, str_replace('.', '', uniqid('', true))))
            ->setPassword('not-used')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsVerified(false);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user, 'website');

        $crawler = $client->request('GET', sprintf('/%s/admin/category/add', $locale));
        $client->submit($crawler->filter('form[name="edit_category_form"]')->form([
            'edit_category_form[title]' => sprintf('Denied %s %s', $locale, str_replace('.', '', uniqid('', true))),
        ]));
        self::assertResponseRedirects();
        $crawler = $client->followRedirect();

        self::assertStringContainsString($messages['denied'], $crawler->filter('.alert-danger')->text());
    }

    public static function provideLocales(): Generator
    {
        yield 'Russian' => ['ru', [
            'invalid' => 'Что-то пошло не так. Проверьте введённые данные!',
            'oppositeInvalid' => 'Something went wrong. Please check!',
            'saved' => 'Изменения сохранены!', 'deleted' => '[Мягкое удаление] Категория',
            'denied' => 'Недостаточно прав. Обратитесь к администратору.',
        ]];

        yield 'English' => ['en', [
            'invalid' => 'Something went wrong. Please check!',
            'oppositeInvalid' => 'Что-то пошло не так. Проверьте введённые данные!',
            'saved' => 'Your changes were saved!', 'deleted' => '[Soft delete] The category',
            'denied' => "You don't have enough rights! Contact the administrator.",
        ]];
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_ADMIN_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user, 'website');

        return $client;
    }
}
