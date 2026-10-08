<?php

declare(strict_types=1);

namespace App\Tests\TestUtils\Account;

use App\Entity\User;
use App\Kernel;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class ResetPasswordPostgreSqlWorker
{
    public static function run(): void
    {
        $input = json_decode(self::readLine(), true, flags: JSON_THROW_ON_ERROR);
        $kernel = new Kernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        $client = $container->get('test.client');
        $client->disableReboot();
        $client->catchExceptions(false);
        $entityManager = $container->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->executeStatement("SET lock_timeout = '15s'");
        $connection->executeStatement("SET statement_timeout = '20s'");
        self::emit([
            'event' => 'ready',
            'pid' => (int) $connection->fetchOne('SELECT pg_backend_pid()'),
            'postgresql' => $connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'version' => (int) $connection->fetchOne('SHOW server_version_num'),
            'isolation' => $connection->fetchOne('SHOW transaction_isolation'),
            'outer_transaction' => $connection->isTransactionActive() || StaticDriver::isKeepStaticConnections(),
        ]);

        $session = $container->get('session.factory')->createSession();
        $session->set('ResetPasswordPublicToken', $input['token']);
        $session->set('ResetPasswordCheckEmail', true);
        $session->set('ResetConcurrencyWorker', $input['worker']);
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
        $hasher = new UserPasswordHasher($container->get('security.password_hasher_factory'));
        $container->set(UserPasswordHasherInterface::class, new class($hasher) implements UserPasswordHasherInterface {
            public function __construct(private UserPasswordHasherInterface $hasher)
            {
            }

            public function hashPassword(PasswordAuthenticatedUserInterface $user, #[\SensitiveParameter] string $plainPassword): string
            {
                $hash = $this->hasher->hashPassword($user, $plainPassword);
                ResetPasswordPostgreSqlWorker::barrier('preliminary');

                return $hash;
            }

            public function isPasswordValid(PasswordAuthenticatedUserInterface $user, #[\SensitiveParameter] string $plainPassword): bool
            {
                return $this->hasher->isPasswordValid($user, $plainPassword);
            }

            public function needsRehash(PasswordAuthenticatedUserInterface $user): bool
            {
                return $this->hasher->needsRehash($user);
            }
        });

        $crawler = $client->request('GET', '/ru/reset-password/reset');
        if (200 !== $client->getResponse()->getStatusCode()) {
            throw new \RuntimeException('Reset form unavailable.');
        }
        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[plainPassword][first]' => $input['password'],
            'change_password_form[plainPassword][second]' => $input['password'],
        ]);
        $listener = new class($input, $hasher) {
            public function __construct(private array $input, private UserPasswordHasherInterface $hasher)
            {
            }

            public function preFlush(PreFlushEventArgs $args): void
            {
                $entityManager = $args->getObjectManager();
                $user = $entityManager->find(User::class, $this->input['user_id']);
                $deleted = 0 === (int) $entityManager->getConnection()->fetchOne(
                    'SELECT COUNT(*) FROM reset_password_request WHERE user_id = ?',
                    [$this->input['user_id']],
                );
                ResetPasswordPostgreSqlWorker::barrier('before_flush', [
                    'deleted' => $deleted,
                    'password_changed' => $user instanceof User && $this->hasher->isPasswordValid($user, $this->input['password']),
                    'in_transaction' => $entityManager->getConnection()->isTransactionActive(),
                ]);
                if ($this->input['rollback']) {
                    throw new ResetPasswordWorkerRollback();
                }
            }
        };
        $entityManager->getEventManager()->addEventListener([Events::preFlush], $listener);
        try {
            $client->submit($form);
            $response = $client->getResponse();
            $outcome = match (true) {
                302 === $response->getStatusCode() && '/ru/' === $response->headers->get('Location') => 'success',
                302 === $response->getStatusCode() && '/ru/reset-password' === $response->headers->get('Location') => 'invalid',
                default => 'unexpected',
            };
        } catch (ResetPasswordWorkerRollback) {
            $outcome = 'rollback';
        } finally {
            $entityManager->getEventManager()->removeEventListener([Events::preFlush], $listener);
        }

        $savedSession = $container->get('session.factory')->createSession();
        $savedSession->setId($session->getId());
        self::emit([
            'event' => 'result',
            'outcome' => $outcome,
            'session_owner' => $savedSession->get('ResetConcurrencyWorker'),
            'token_retained' => $savedSession->has('ResetPasswordPublicToken'),
            'check_email_retained' => $savedSession->has('ResetPasswordCheckEmail'),
            'safe_flash' => $savedSession->getFlashBag()->has('reset_password_error'),
            'transaction_active' => $connection->isTransactionActive(),
        ]);
        $savedSession->save();
        $kernel->shutdown();
    }

    public static function barrier(string $event, array $details = []): void
    {
        self::emit(['event' => $event] + $details);
        if ('continue' !== self::readLine()) {
            throw new \RuntimeException('Invalid barrier command.');
        }
    }

    private static function emit(array $event): void
    {
        fwrite(STDOUT, json_encode($event, JSON_THROW_ON_ERROR)."\n");
        fflush(STDOUT);
    }

    private static function readLine(): string
    {
        $read = [STDIN];
        $write = $except = [];
        if (1 !== stream_select($read, $write, $except, 25)) {
            throw new \RuntimeException('Worker barrier deadline exceeded.');
        }
        $line = fgets(STDIN);
        if (false === $line) {
            throw new \RuntimeException('Worker input closed.');
        }

        return trim($line);
    }
}

final class ResetPasswordWorkerRollback extends \RuntimeException
{
}

if (__FILE__ === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require dirname(__DIR__, 2).'/bootstrap.php';
    try {
        ResetPasswordPostgreSqlWorker::run();
    } catch (\Throwable $exception) {
        // EN: Report only the exception type; diagnostic messages can contain credentials or tokens.
        // RU: Выводим только тип исключения: диагностические сообщения могут содержать credentials или token.
        fwrite(STDOUT, json_encode(['event' => 'error', 'type' => $exception::class], JSON_THROW_ON_ERROR)."\n");
        exit(1);
    }
}
