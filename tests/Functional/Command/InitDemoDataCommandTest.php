<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InitDemoDataCommand;
use App\Demo\DemoDataInitializer;
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
        self::assertStringContainsString('Products: 0 created, 0 updated, 24 existing', $output);
        self::assertStringContainsString('Orders: 24 removed, 24 created', $output);
        self::assertStringContainsString('Order products: 48 removed, 48 created', $output);
    }

    public function testCommandRefusesProductionBeforeInitialization(): void
    {
        self::bootKernel();
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');
        $command = new InitDemoDataCommand(self::getContainer()->get(DemoDataInitializer::class), $kernel);
        $tester = new CommandTester($command);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('allowed only in dev and test environments', $tester->getDisplay());
    }
}
