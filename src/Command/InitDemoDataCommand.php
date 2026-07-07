<?php

declare(strict_types=1);

namespace App\Command;

use App\Demo\DemoDataInitializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

class InitDemoDataCommand extends Command
{
    private const ALLOWED_ENVIRONMENTS = ['dev', 'test'];

    public function __construct(
        private DemoDataInitializer $demoDataInitializer,
        private KernelInterface $kernel
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:demo:init')
            ->setDescription('Create or update minimal dev/test demo data.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $environment = $this->kernel->getEnvironment();

        if (!in_array($environment, self::ALLOWED_ENVIRONMENTS, true)) {
            $io->error(sprintf(
                'The app:demo:init command is allowed only in dev and test environments. Current environment: %s.',
                $environment
            ));

            return Command::FAILURE;
        }

        try {
            $result = $this->demoDataInitializer->initialize();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Demo data was initialized.');
        $io->section('Demo accounts');
        foreach ($result['credentials'] as $credentials) {
            $io->writeln(sprintf('%s / %s', $credentials['email'], $credentials['password']));
        }

        $io->section('Created / updated');
        $io->writeln(sprintf('Users: %d created, %d updated', $result['users']['created'], $result['users']['updated']));
        $io->writeln(sprintf('Categories: %d created, %d updated', $result['categories']['created'], $result['categories']['updated']));
        $io->writeln(sprintf('Products: %d created, %d updated', $result['products']['created'], $result['products']['updated']));
        $io->writeln(sprintf('Images: %d created, %d already present', $result['images']['created'], $result['images']['existing']));
        $io->writeln(sprintf('Image files copied: %d', $result['images']['files_copied']));

        return Command::SUCCESS;
    }
}
