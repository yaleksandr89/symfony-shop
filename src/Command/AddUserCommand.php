<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class AddUserCommand extends Command
{
    // >> Autowiring
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $em;

    /**
     * @required
     * @param EntityManagerInterface $em
     * @return AddUserCommand
     */
    public function setEm(EntityManagerInterface $em): AddUserCommand
    {
        $this->em = $em;
        return $this;
    }

    /**
     * @var UserPasswordHasherInterface
     */
    private UserPasswordHasherInterface $encoder;

    /**
     * @required
     * @param UserPasswordHasherInterface $encoder
     * @return AddUserCommand
     */
    public function setEncoder(UserPasswordHasherInterface $encoder): AddUserCommand
    {
        $this->encoder = $encoder;
        return $this;
    }

    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;

    /**
     * @required
     * @param UserRepository $userRepository
     * @return AddUserCommand
     */
    public function setUserRepository(UserRepository $userRepository): AddUserCommand
    {
        $this->userRepository = $userRepository;
        return $this;
    }
    // Autowiring <<<

    /**
     * @var string
     */
    protected static $defaultName = 'app:add-user';

    /**
     * @var string
     */
    protected static $defaultDescription = 'Create user';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('email', 'email', InputArgument::REQUIRED, 'Enter email')
            ->addOption('password', 'password', InputArgument::REQUIRED, 'Enter password')
            ->addOption('isAdmin', '', InputArgument::OPTIONAL, 'If set the user is created as an administrator', 0);
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
        $stopWatch->start('add-user-command');

        $email = $input->getOption('email');
        $password = $input->getOption('password');
        $isAdmin = $input->getOption('isAdmin');

        $io->title('Add User Command Wizard');
        $io->text(['Please, enter some information:']);

//        $email = $this->checkingEmail($email, $io);
//        $password = $this->checkingPassword($password, $io);
//        $isAdmin = $this->checkingAdmin($isAdmin, $io);

        if (!$email) {
            $email = $io->ask('Email');
        }

        if (!$password) {
            $password = $io->askHidden('Password (your type will be hidden)');
        }
        if (!$isAdmin) {
            $isAdminQuestion = new Question('Is admin? (1 or 0)', 0);
            $isAdmin = $io->askQuestion($isAdminQuestion);
        }

        try {
            $user = $this->createUser($email, $password, (bool)$isAdmin);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $successMessage = sprintf(
            '%s was successfully created: %s',
            $isAdmin ? 'Administrator' : 'User',
            $email
        );

        $io->success($successMessage);

        $event = $stopWatch->stop('add-user-command');
        $stopWatchMessage = sprintf(
            'New user\'s id: %s / Elapsed time: %.2f ms / Consumed memory: %.2f MB',
            $user->getId(),
            $event->getDuration(),
            number_format($event->getMemory() / 1048576, 2)
        );
        $io->comment($stopWatchMessage);

        return Command::SUCCESS;
    }

    private function createUser(string $email, string $password, bool $isAdmin): User
    {
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);

        if ($existingUser) {
            throw new RuntimeException('User already exist');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles([$isAdmin ? 'ROLE_ADMIN' : 'ROLE_USER']);

        $encodedPassword = $this->encoder->hashPassword($user, $password);
        $user->setPassword($encodedPassword);

        $user->setIsVerified(true);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

//    /**
//     * @param bool $email
//     * @param SymfonyStyle $io
//     * @return string|null
//     */
//    private function checkingEmail(bool $email, SymfonyStyle $io): ?string
//    {
//        if (!$email) {
//            return $io->ask('Email');
//        }
//        return null;
//    }
//
//    /**
//     * @param bool $password
//     * @param SymfonyStyle $io
//     * @return string|null
//     */
//    private function checkingPassword(bool $password, SymfonyStyle $io): ?string
//    {
//        if (!$password) {
//            return $io->askHidden('Password (your type will be hidden)');
//        }
//        return null;
//    }
//
//    /**
//     * @param int $isAdmin
//     * @param SymfonyStyle $io
//     * @return int|null
//     */
//    private function checkingAdmin(int $isAdmin, SymfonyStyle $io): ?int
//    {
//        if (!$isAdmin) {
//            $isAdminQuestion = new Question('Is admin? (1 or 0)', 0);
//            return $io->askQuestion($isAdminQuestion);
//        }
//        return null;
//    }
}
