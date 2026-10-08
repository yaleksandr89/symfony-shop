<?php

declare(strict_types=1);

namespace App\Tests\Integration\Account\Controller;

use App\Entity\User;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Group('reset-password-postgresql')]
final class ResetPasswordConcurrencyPostgreSqlTest extends KernelTestCase
{
    private Connection $observer;

    /** @var array<string, array{process: resource, pipes: array<int, resource>}> */
    private array $workers = [];

    protected function setUp(): void
    {
        if (!defined('RESET_PASSWORD_POSTGRESQL_SUITE')) {
            self::markTestSkipped('Opt-in suite: make test-reset-password-postgresql CONFIRM=testdb');
        }
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->observer = $entityManager->getConnection();
        self::assertInstanceOf(PostgreSQLPlatform::class, $this->observer->getDatabasePlatform(), 'Dedicated PostgreSQL target required.');
        self::assertSame('reset_password_concurrency_test', $this->observer->fetchOne('SELECT current_database()'));
        self::assertSame(18, intdiv((int) $this->observer->fetchOne('SHOW server_version_num'), 10000));
        self::assertSame('read committed', $this->observer->fetchOne('SHOW transaction_isolation'));
        self::assertFalse(StaticDriver::isKeepStaticConnections(), 'DAMA outer transaction extension must be absent.');
        self::assertFalse($this->observer->isTransactionActive());
        (new SchemaTool($entityManager))->updateSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if (proc_get_status($worker['process'])['running']) {
                proc_terminate($worker['process']);
            }
            foreach ($worker['pipes'] as $pipe) {
                fclose($pipe);
            }
            proc_close($worker['process']);
        }
        $this->workers = [];
        parent::tearDown();
    }

    /** @return iterable<string, array{string, bool}> */
    public static function workerOrderings(): iterable
    {
        yield 'A commits, B loses' => ['A', false];
        yield 'B commits, A loses' => ['B', false];
        yield 'A rolls back, B commits' => ['A', true];
        yield 'B rolls back, A commits' => ['B', true];
    }

    #[DataProvider('workerOrderings')]
    #[TestDox('Один token допускает один commit; откат сохраняет token для ожидающего запроса ($first, rollback=$rollback)')]
    public function testTokenCanCommitOnlyOnceAndSurvivesRollback(string $first, bool $rollback): void
    {
        $second = 'A' === $first ? 'B' : 'A';
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user = (new User())->setEmail('reset-concurrency-'.bin2hex(random_bytes(8)).'@example.test')->setIsVerified(true);
        $user->setPassword($hasher->hashPassword($user, 'original-password'));
        $entityManager->persist($user);
        $entityManager->flush();
        $userId = $user->getId();
        self::assertNotNull($userId);
        $originalHash = $user->getPassword();
        $helper = self::getContainer()->get(ResetPasswordHelperInterface::class);
        $helper->generateResetToken($user, -1);
        $token = $helper->generateResetToken($user)->getToken();
        self::assertSame(2, $this->requestCount($userId));
        $passwords = ['A' => 'candidate-password-A', 'B' => 'candidate-password-B'];

        foreach (['A', 'B'] as $name) {
            $this->startWorker($name, [
                'user_id' => $userId,
                'worker' => $name,
                'token' => $token,
                'password' => $passwords[$name],
                'rollback' => $rollback && $name === $first,
            ]);
        }
        $pids = [];
        foreach (['A', 'B'] as $name) {
            $ready = $this->event($name, 'ready');
            self::assertTrue($ready['postgresql']);
            self::assertSame(18, intdiv($ready['version'], 10000));
            self::assertSame('read committed', $ready['isolation']);
            self::assertFalse($ready['outer_transaction']);
            $pids[$name] = $ready['pid'];
            $this->event($name, 'preliminary');
        }
        self::assertNotSame($pids['A'], $pids['B']);
        self::assertNotContains((int) $this->observer->fetchOne('SELECT pg_backend_pid()'), $pids);

        $this->release($first);
        $locked = $this->event($first, 'before_flush');
        self::assertTrue($locked['deleted']);
        self::assertTrue($locked['password_changed']);
        self::assertTrue($locked['in_transaction']);
        $this->release($second);
        $this->awaitBlockedBy($pids[$second], $pids[$first]);
        self::assertSame($originalHash, $this->observer->fetchOne('SELECT password FROM "user" WHERE id = ?', [$userId]));
        self::assertSame(2, $this->requestCount($userId));

        $this->release($first);
        $firstResult = $this->event($first, 'result');
        if ($rollback) {
            self::assertSame('rollback', $firstResult['outcome']);
            self::assertTrue($firstResult['token_retained']);
            self::assertTrue($firstResult['check_email_retained']);
            $locked = $this->event($second, 'before_flush');
            self::assertTrue($locked['deleted']);
            self::assertTrue($locked['password_changed']);
            self::assertTrue($locked['in_transaction']);
            self::assertSame($originalHash, $this->observer->fetchOne('SELECT password FROM "user" WHERE id = ?', [$userId]));
            self::assertSame(2, $this->requestCount($userId));
            $this->release($second);
        }
        $secondResult = $this->event($second, 'result');
        $results = [$first => $firstResult, $second => $secondResult];
        self::assertCount(1, array_filter($results, static fn (array $result): bool => 'success' === $result['outcome']));
        $winner = $rollback ? $second : $first;
        $loser = $rollback ? $first : $second;
        self::assertSame('success', $results[$winner]['outcome']);
        self::assertFalse($results[$winner]['token_retained']);
        self::assertFalse($results[$winner]['check_email_retained']);
        if (!$rollback) {
            self::assertSame('invalid', $results[$loser]['outcome']);
            self::assertTrue($results[$loser]['safe_flash']);
            self::assertTrue($results[$loser]['token_retained']);
            self::assertTrue($results[$loser]['check_email_retained']);
        }
        foreach ($results as $name => $result) {
            self::assertSame($name, $result['session_owner']);
            self::assertFalse($result['transaction_active']);
        }
        $entityManager->clear();
        $persistedUser = $entityManager->find(User::class, $userId);
        self::assertInstanceOf(User::class, $persistedUser);
        self::assertTrue($hasher->isPasswordValid($persistedUser, $passwords[$winner]));
        self::assertFalse($hasher->isPasswordValid($persistedUser, $passwords[$loser]));
        self::assertFalse($hasher->isPasswordValid($persistedUser, 'original-password'));
        self::assertSame(0, $this->requestCount($userId));
        foreach (['A', 'B'] as $name) {
            // EN: Exit status and stderr are checked without displaying potentially sensitive diagnostics.
            // RU: Проверяем код завершения и stderr без вывода потенциально чувствительной диагностики.
            self::assertTrue('' === stream_get_contents($this->workers[$name]['pipes'][2]), 'Worker emitted diagnostics.');
            foreach ($this->workers[$name]['pipes'] as $pipe) {
                fclose($pipe);
            }
            self::assertSame(0, proc_close($this->workers[$name]['process']));
            unset($this->workers[$name]);
        }
    }

    private function requestCount(int $userId): int
    {
        return (int) $this->observer->fetchOne('SELECT COUNT(*) FROM reset_password_request WHERE user_id = ?', [$userId]);
    }

    private function startWorker(string $name, array $input): void
    {
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=stderr', 'tests/TestUtils/Account/ResetPasswordPostgreSqlWorker.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            self::getContainer()->getParameter('kernel.project_dir'),
        );
        self::assertIsResource($process);
        $this->workers[$name] = ['process' => $process, 'pipes' => $pipes];
        fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR)."\n");
        fflush($pipes[0]);
    }

    private function event(string $name, string $expected): array
    {
        $read = [$this->workers[$name]['pipes'][1]];
        $write = $except = [];
        self::assertSame(1, stream_select($read, $write, $except, 25), 'Worker event deadline exceeded: '.$name.' '.$expected);
        $line = fgets($read[0]);
        self::assertNotFalse($line, 'Worker output closed: '.$name);
        $event = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if ('error' === $event['event']) {
            self::fail('Worker failed: '.$event['type']);
        }
        self::assertSame($expected, $event['event'], 'Unexpected worker stage: '.$name);

        return $event;
    }

    private function release(string $name): void
    {
        fwrite($this->workers[$name]['pipes'][0], "continue\n");
        fflush($this->workers[$name]['pipes'][0]);
    }

    private function awaitBlockedBy(int $waiter, int $holder): void
    {
        $deadline = microtime(true) + 10;
        do {
            if (1 === (int) $this->observer->fetchOne(
                "SELECT COUNT(*) FROM pg_stat_activity WHERE pid = ? AND wait_event_type = 'Lock' AND ? = ANY(pg_blocking_pids(pid)) AND query LIKE '%FOR UPDATE%' AND query LIKE '%\"user\"%'",
                [$waiter, $holder],
            )) {
                return;
            }
            // EN: Poll the observable database lock with bounded IPC waits, never a timing-based release.
            // RU: Проверяем реальную блокировку БД с ограниченным ожиданием IPC, без освобождения по таймеру.
            $read = array_map(static fn (array $worker) => $worker['pipes'][1], $this->workers);
            $write = $except = [];
            self::assertSame(0, stream_select($read, $write, $except, 0, 20000), 'Worker completed before lock contention was observed.');
        } while (microtime(true) < $deadline);

        self::fail('PostgreSQL user lock contention was not observed before the deadline.');
    }
}
