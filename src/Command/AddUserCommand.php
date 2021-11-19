<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
    private UserPasswordHasherInterface $hasher;

    /**
     * @required
     * @param UserPasswordHasherInterface $hasher
     * @return AddUserCommand
     */
    public function setEncoder(UserPasswordHasherInterface $hasher): AddUserCommand
    {
        $this->hasher = $hasher;
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
            ->addOption('role', '', InputArgument::REQUIRED, 'Set role');
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
        $role = $input->getOption('role');

        $io->title('Add User Command Wizard');
        $io->text(['Please, enter some information:']);

        $email = $this->checkingEmail($email, $io);
        $password = $this->checkingPassword($password, $io);
        $assignedRole = $this->checkingRole($role, $io);

        try {
            $user = $this->createUser($email, $password, $assignedRole);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $successMessage = sprintf(
            'User was successfully created: email[%s], password[%s], role[%s]',
            $email,
            $password,
            $assignedRole,
        );
        $io->success($successMessage);

        $event = $stopWatch->stop('add-user-command');
        $stopWatchMessage = sprintf(
            'New user\'s id: %s / Elapsed time: %.2f s / Consumed memory: %.2f MB',
            $user->getId(),
            number_format($event->getDuration() / 1000, 2),
            number_format($event->getMemory() / 1048576, 2)
        );
        $io->comment($stopWatchMessage);

        return Command::SUCCESS;
    }

    /**
     * @param string $email
     * @param string $password
     * @param string $role
     * @return User
     */
    private function createUser(string $email, string $password, string $role): User
    {
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);

        if ($existingUser) {
            throw new RuntimeException('User already exist');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles([$role]);

        $hashPassword = $this->hasher->hashPassword($user, $password);
        $user->setPassword($hashPassword);

        $user->setIsVerified(true);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @param bool $email
     * @param SymfonyStyle $io
     * @return string|null
     */
    private function checkingEmail(bool $email, SymfonyStyle $io): ?string
    {
        if (!$email) {
            $isEmail = false;

            while (!$isEmail) {
                $email = $io->ask('Email');
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $isEmail = true;
                } else {
                    $io->error('Incorrect email format, repeat the input');
                }
            }
            return $email;
        }
        return null;
    }

    /**
     * @param bool $password
     * @param SymfonyStyle $io
     * @return string|null
     */
    private function checkingPassword(bool $password, SymfonyStyle $io): ?string
    {
        if (!$password) {
            $isPassword = false;

            while (!$isPassword) {
                $password = $io->askHidden('Password (your type will be hidden)');
                if (mb_strlen($password) >= 6) {
                    $isPassword = true;
                } else {
                    $io->error('The password can\'t be less than 6 characters');
                }
            }
            return $password;
        }
        return null;
    }

    /**
     * @param bool $role
     * @param SymfonyStyle $io
     * @return string|null
     */
    private function checkingRole(bool $role, SymfonyStyle $io): ?string
    {
        if (!$role) {
            $assignedRole = '';

            while ('' === $assignedRole) {
                //$roleQuestion = new Question('Set role?', 'ROLE_USER');
                $roleQuestion = $io->ask('Set role?', 'ROLE_USER');
                //$role = $io->askQuestion($roleQuestion);

                if ($roleQuestion === 'ROLE_USER' || $roleQuestion === 'ROLE_ADMIN' || $roleQuestion === 'ROLE_SUPER_ADMIN') {
                    $assignedRole = $roleQuestion;
                } else {
                    $io->error('Please, enter role user or leave empty (default = ROLE_USER)');
                }
            }
            return $assignedRole;
        }
        return null;
    }
}
