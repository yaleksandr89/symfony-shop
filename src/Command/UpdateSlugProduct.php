<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface as Doctrine;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

class UpdateSlugProduct extends Command
{
    // >> Autowiring
    /**
     * @var Doctrine
     */
    private Doctrine $doctrine;

    /**
     * @required
     * @param Doctrine $doctrine
     * @return UpdateSlugProduct
     */
    public function setDoctrine(Doctrine $doctrine): UpdateSlugProduct
    {
        $this->doctrine = $doctrine;
        return $this;
    }
    // Autowiring <<<


    /**
     * @var string
     */
    protected static $defaultName = 'app:update-slug-product';

    /**
     * @var string
     */
    protected static $defaultDescription = 'Update slug product';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('all', 'a', InputArgument::OPTIONAL, 'Updated slug for all product, not just products where slug=null', '0');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        $io = new SymfonyStyle($input, $output);

        $stopWatch = new Stopwatch();
        $stopWatch->start('update-slug-product');

        $all = (bool)$input->getOption('all');

        $io->title('Update slug product');

        try {
            $updateCountProduct = $this->updateSlugProduct($all);
            $updateMessage = $all
                ? 'Updated slug for all product complete'
                : 'Updated slug for product, where product.slug=null';

            $io->success($updateMessage);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }


        $event = $stopWatch->stop('update-slug-product');
        $stopWatchMessage = sprintf(
            'Updated %d products | Elapsed time: %.2f s | Consumed memory: %.2f MB',
            $updateCountProduct,
            number_format($event->getDuration() / 1000, 2),
            number_format($event->getMemory() / 1048576, 2)
        );
        $io->comment($stopWatchMessage);

        return Command::SUCCESS;
    }

    /**
     * @param bool $all
     * @return int
     */
    private function updateSlugProduct(bool $all): int
    {
        $productRepository = $this->doctrine->getRepository(Product::class);

        if ($all) {
            $products = $productRepository->findAll();
        } else {
            $products = $productRepository->findBy(['slug' => null]);
        }

        /** @var Product $product */
        foreach ($products as $product) {
            $this->doctrine->persist($product);
            $product->setSlug($product->getTitle());
        }
        $this->doctrine->flush();

        return count($products);
    }
}