<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InitDemoDataCommand;
use App\Demo\DemoDataInitializer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

#[Group(name: 'functional')]
class InitDemoDataCommandTest extends KernelTestCase
{
    public function testCommandReportsCountersAndDemoCredentialsInTest(): void
    {
        self::bootKernel();
        $command = self::getContainer()->get(InitDemoDataCommand::class);
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $output = $tester->getDisplay();
        self::assertStringContainsString('super-admin@example.test / DemoSuperAdmin123!', $output);
        self::assertStringContainsString('admin@example.test / DemoAdmin123!', $output);
        self::assertStringContainsString('user@example.test / DemoUser123!', $output);
        self::assertStringContainsString('Products: 0 created, 0 updated, 48 existing', $output);
        self::assertStringContainsString('Orders: 24 removed, 24 created', $output);
        self::assertStringContainsString('Order products: 48 removed, 48 created', $output);

        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame(48, (int) $connection->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%'"));
        self::assertSame(11, (int) $connection->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%' AND is_new = true"));
        self::assertSame(8, (int) $connection->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%' AND is_on_sale = true"));
        self::assertSame(29, (int) $connection->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%' AND is_new = false AND is_on_sale = false"));
        self::assertSame(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%' AND is_new = true AND is_on_sale = true"));
    }

    public function testCommandRefusesProductionBeforeInitialization(): void
    {
        self::bootKernel();
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');
        $command = new InitDemoDataCommand(self::getContainer()->get(DemoDataInitializer::class), $kernel);
        $tester = new CommandTester($command);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('allowed only in dev and test environments', $tester->getDisplay());
    }
}
