<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group(name: 'functional')]
class ProductControllerTest extends WebTestCase
{
    public function testListLoads(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/ru/admin/product/list');

        self::assertResponseIsSuccessful();
    }

    public function testEmptyFilterSubmitDoesNotFail(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/product/list');
        $client->submit($crawler->selectButton('Apply')->form());

        self::assertResponseIsSuccessful();
    }

    public function testPriceRangeFilterDoesNotFail(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/ru/admin/product/list');
        $form = $crawler->selectButton('Apply')->form([
            'order_filter_form[price][left_number]' => '10',
            'order_filter_form[price][right_number]' => '100',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => UserFixtures::USER_ADMIN_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user, 'website');

        return $client;
    }
}
