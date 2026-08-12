<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\InitDemoDataCommand;
use App\Demo\DemoDataInitializer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

#[Group(name: 'functional')]
class InitDemoDataCommandTest extends KernelTestCase
{
    #[TestDox('Команда повторно выполняется и сообщает учётные данные и сводные счётчики')]
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
        foreach (['Users', 'Categories', 'Products', 'Images'] as $heading) {
            self::assertMatchesRegularExpression(
                sprintf('/%s: \d+ created, \d+ updated, \d+ existing/', $heading),
                $output,
            );
        }
        self::assertMatchesRegularExpression('/Image files: \d+ copied, \d+ updated, \d+ existing/', $output);
        self::assertMatchesRegularExpression('/Orders: \d+ removed, \d+ created/', $output);
        self::assertMatchesRegularExpression('/Order products: \d+ removed, \d+ created/', $output);
    }

    #[TestDox('Команда не запускается в production до инициализации данных')]
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
