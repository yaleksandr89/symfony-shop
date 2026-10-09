<?php

declare(strict_types=1);

namespace App\Tests\Integration\OAuthBundle\Security;

use App\Entity\User;
use App\OAuthBundle\Security\OAuth\OAuthAccountLinker;
use App\OAuthBundle\Security\OAuth\OAuthIdentityAccessor;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
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

#[Group('oauth-postgresql')]
final class OAuthIdentityMutationConcurrencyPostgreSqlTest extends KernelTestCase
{
    private Connection $observer;

    /** @var array<string, array{process: resource, pipes: array<int, resource>}> */
    private array $workers = [];

    protected function setUp(): void
    {
        if (!defined('OAUTH_POSTGRESQL_SUITE')) {
            self::markTestSkipped('Opt-in suite: make test-oauth-postgresql CONFIRM=testdb');
        }
        self::bootKernel();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $this->observer = $manager->getConnection();
        self::assertInstanceOf(PostgreSQLPlatform::class, $this->observer->getDatabasePlatform());
        self::assertSame('oauth_identity_concurrency_test', $this->observer->fetchOne('SELECT current_database()'));
        self::assertSame(180004, (int) $this->observer->fetchOne('SHOW server_version_num'));
        self::assertSame('read committed', $this->observer->fetchOne('SHOW transaction_isolation'));
        self::assertFalse(StaticDriver::isKeepStaticConnections());
        self::assertFalse($this->observer->isTransactionActive());
        (new SchemaTool($manager))->updateSchema($manager->getMetadataFactory()->getAllMetadata());
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

    /** @return iterable<string, array{string, bool, bool}> */
    public static function raceOrderings(): iterable
    {
        foreach (['A', 'B'] as $first) {
            yield $first.' commits' => [$first, false, false];
            yield $first.' rolls back' => [$first, true, false];
            yield 'GitHub '.$first.' commits' => [$first, false, true];
        }
    }

    #[DataProvider('raceOrderings')]
    #[TestDox('A: Два аккаунта не могут присвоить один внешний ID; SQL конфликт безопасно восстанавливает сессию')]
    public function testCrossUserUniqueContentionAndCallbackRecovery(string $first, bool $rollback, bool $github): void
    {
        $second = 'A' === $first ? 'B' : 'A';
        $providers = $this->providers($github);
        $users = ['A' => $this->user(), 'B' => $this->user()];
        $externalId = 'shared-'.bin2hex(random_bytes(8));
        foreach (['A', 'B'] as $name) {
            $this->start($name, $users[$name], $providers[$name], 'link', $externalId, null, $rollback && $name === $first);
        }
        $pids = $this->readyBoth();
        $this->release($first);
        $this->assertBeforeFlush($first);
        $this->release($second);
        $this->assertBeforeFlush($second);
        $this->release($first);
        self::assertTrue($this->event($first, 'written')['in_transaction']);
        $this->release($second);
        $this->awaitBlockedBy($pids[$second], $pids[$first], 'UPDATE');
        self::assertNull($this->identity($users[$first], $providers[$first]));
        self::assertNull($this->identity($users[$second], $providers[$second]));
        $this->release($first);
        $firstResult = $this->event($first, 'result');
        if ($rollback) {
            self::assertTrue($this->event($second, 'written')['in_transaction']);
            $this->release($second);
        }
        $secondResult = $this->event($second, 'result');
        $results = [$first => $firstResult, $second => $secondResult];
        $winner = $rollback ? $second : $first;
        $loser = $rollback ? $first : $second;
        self::assertSame('success', $results[$winner]['outcome']);
        self::assertSame('conflict', $results[$loser]['outcome']);
        self::assertTrue($results[$loser]['generic_failure']);
        self::assertTrue($results[$loser]['closed_observed']);
        self::assertSame(1, $results[$loser]['resets']);
        self::assertTrue($results[$loser]['unit_of_work_replaced']);
        self::assertSame(0, $results[$winner]['resets']);
        self::assertSame($externalId, $this->identity($users[$winner], $providers[$winner]));
        self::assertNull($this->identity($users[$loser], $providers[$loser]));
        foreach ($results as $name => $result) {
            $this->assertRecoveredResult($name, $result, true);
        }
        $this->finishWorkers();
    }

    #[DataProvider('raceOrderings')]
    #[TestDox('B: Устаревшие callbacks одного аккаунта не перезаписывают победителя после ожидания row lock')]
    public function testSameUserDifferentIdentitiesCannotBothCommit(string $first, bool $rollback, bool $github): void
    {
        $second = 'A' === $first ? 'B' : 'A';
        $providers = $this->providers($github);
        $user = $this->user();
        $ids = ['A' => 'candidate-A-'.bin2hex(random_bytes(8)), 'B' => 'candidate-B-'.bin2hex(random_bytes(8))];
        foreach (['A', 'B'] as $name) {
            $this->start($name, $user, $providers[$name], 'link', $ids[$name], null, $rollback && $name === $first);
        }
        $pids = $this->readyBoth();
        $this->release($first);
        $this->assertBeforeFlush($first);
        $this->release($second);
        $this->awaitBlockedBy($pids[$second], $pids[$first], 'FOR UPDATE');
        self::assertNull($this->identity($user, $providers[$first]));
        $this->release($first);
        self::assertTrue($this->event($first, 'written')['in_transaction']);
        $this->release($first);
        $firstResult = $this->event($first, 'result');
        if ($rollback) {
            $this->assertBeforeFlush($second);
            $this->release($second);
            self::assertTrue($this->event($second, 'written')['in_transaction']);
            $this->release($second);
        }
        $secondResult = $this->event($second, 'result');
        $results = [$first => $firstResult, $second => $secondResult];
        $winner = $rollback ? $second : $first;
        $loser = $rollback ? $first : $second;
        self::assertSame('success', $results[$winner]['outcome']);
        self::assertSame('conflict', $results[$loser]['outcome']);
        self::assertTrue($results[$loser]['generic_failure']);
        self::assertSame($rollback ? 1 : 0, $results[$loser]['resets']);
        self::assertSame($rollback, $results[$loser]['closed_observed']);
        self::assertSame($ids[$winner], $this->identity($user, $providers[$winner]));
        foreach ($results as $name => $result) {
            $this->assertRecoveredResult($name, $result, true);
        }
        $this->finishWorkers();
    }

    #[TestDox('C: POST старой отвязки не удаляет новую привязку после последовательной смены')]
    public function testDelayedUnlinkCannotEraseSequentiallyLinkedReplacement(): void
    {
        $user = $this->user();
        $linker = self::getContainer()->get(OAuthAccountLinker::class);
        $oldId = 'observed-old-'.$user->getId();
        $newId = 'replacement-new-'.$user->getId();
        $linker->link($user, OAuthProvider::GithubEn, $oldId);
        $this->start('A', $user, OAuthProvider::GithubEn, 'unlink', '', $oldId, false);
        $this->assertReady($this->event('A', 'ready'));
        self::assertFalse($this->event('A', 'preliminary')['in_transaction']);
        $linker->unlink($user, OAuthProvider::GithubRus, $oldId);
        $linker->link($user, OAuthProvider::GithubRus, $newId);
        $this->release('A');
        $result = $this->event('A', 'result');
        self::assertSame('conflict', $result['outcome']);
        self::assertTrue($result['generic_failure']);
        self::assertSame(0, $result['resets']);
        self::assertSame($newId, $this->identity($user, OAuthProvider::GithubEn));
        $this->assertRecoveredResult('A', $result, false);
        $this->finishWorkers();
    }

    /** @return iterable<string, array{string}> */
    public static function unlinkOrderings(): iterable
    {
        yield 'A first' => ['A'];
        yield 'B first' => ['B'];
    }

    #[DataProvider('unlinkOrderings')]
    #[TestDox('D: Две подтверждённые отвязки одного ID завершаются NULL без потери новой привязки')]
    public function testDoubleUnlinkIsAnIdempotentNoOpForTheWaiter(string $first): void
    {
        $second = 'A' === $first ? 'B' : 'A';
        $user = $this->user();
        $externalId = 'observed-double-'.$user->getId();
        self::getContainer()->get(OAuthAccountLinker::class)->link($user, OAuthProvider::Google, $externalId);
        foreach (['A', 'B'] as $name) {
            $this->start($name, $user, OAuthProvider::Google, 'unlink', '', $externalId, false);
        }
        $pids = $this->readyBoth();
        $this->release($first);
        $this->assertBeforeFlush($first);
        $this->release($second);
        $this->awaitBlockedBy($pids[$second], $pids[$first], 'FOR UPDATE');
        $this->release($first);
        self::assertTrue($this->event($first, 'written')['in_transaction']);
        $this->release($first);
        $firstResult = $this->event($first, 'result');
        $waiting = $this->event($second, 'before_flush');
        self::assertTrue($waiting['in_transaction']);
        self::assertFalse($waiting['refreshed_matches_expected']);
        $this->release($second);
        self::assertTrue($this->event($second, 'written')['in_transaction']);
        $this->release($second);
        $secondResult = $this->event($second, 'result');
        foreach ([$first => $firstResult, $second => $secondResult] as $name => $result) {
            self::assertSame('success', $result['outcome']);
            self::assertSame(0, $result['resets']);
            $this->assertRecoveredResult($name, $result, false);
        }
        self::assertNull($this->identity($user, OAuthProvider::Google));
        $this->finishWorkers();
    }

    private function user(): User
    {
        $user = (new User())->setEmail('oauth-race-'.bin2hex(random_bytes(8)).'@example.test')->setIsVerified(true)->setFullName('Preserved name');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'synthetic-current-password'));
        $user->setGoogleId(null);
        $user->setYandexId('unrelated-'.bin2hex(random_bytes(8)));
        $user->setVkontakteId(null);
        $user->setGithubId(null);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($user);
        $manager->flush();

        return $user;
    }

    /** @return array<string, OAuthProvider> */
    private function providers(bool $github): array
    {
        return $github ? ['A' => OAuthProvider::GithubEn, 'B' => OAuthProvider::GithubRus] : ['A' => OAuthProvider::Google, 'B' => OAuthProvider::Google];
    }

    private function identity(User $user, OAuthProvider $provider): ?string
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $column = $manager->getClassMetadata(User::class)->getColumnName((new OAuthIdentityAccessor())->identityField($provider));
        self::assertSame('Preserved name', $this->observer->fetchOne('SELECT full_name FROM "user" WHERE id = ?', [$user->getId()]));
        self::assertSame($user->getYandexId(), $this->observer->fetchOne('SELECT yandex_id FROM "user" WHERE id = ?', [$user->getId()]));

        return $this->observer->fetchOne('SELECT '.$column.' FROM "user" WHERE id = ?', [$user->getId()]);
    }

    private function start(string $name, User $user, OAuthProvider $provider, string $action, string $externalId, ?string $observedId, bool $rollback): void
    {
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', 'tests/TestUtils/OAuth/OAuthIdentityMutationPostgreSqlWorker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::getContainer()->getParameter('kernel.project_dir'));
        self::assertIsResource($process);
        $this->workers[$name] = ['process' => $process, 'pipes' => $pipes];
        fwrite($pipes[0], json_encode([
            'worker' => $name, 'user_id' => $user->getId(), 'provider' => $provider->value,
            'action' => $action, 'external_id' => $externalId, 'observed_id' => $observedId, 'rollback' => $rollback,
        ], JSON_THROW_ON_ERROR)."\n");
        fflush($pipes[0]);
    }

    /** @return array<string, int> */
    private function readyBoth(): array
    {
        $pids = [];
        foreach (['A', 'B'] as $name) {
            $ready = $this->event($name, 'ready');
            $this->assertReady($ready);
            $pids[$name] = $ready['pid'];
            self::assertFalse($this->event($name, 'preliminary')['in_transaction']);
        }
        self::assertNotSame($pids['A'], $pids['B']);
        self::assertNotContains((int) $this->observer->fetchOne('SELECT pg_backend_pid()'), $pids);

        return $pids;
    }

    private function assertReady(array $event): void
    {
        self::assertSame(180004, $event['version']);
        self::assertSame('read committed', $event['isolation']);
        self::assertFalse($event['outer_transaction']);
        self::assertTrue($event['observed_matches']);
    }

    private function assertBeforeFlush(string $name): void
    {
        $event = $this->event($name, 'before_flush');
        self::assertTrue($event['in_transaction']);
        self::assertTrue($event['refreshed_matches_expected']);
    }

    private function assertRecoveredResult(string $name, array $result, bool $link): void
    {
        foreach (['redirect', 'manager_open', 'same_account', 'managed_snapshot', 'snapshot_matches_database', 'next_authenticated', 'roles_unchanged', 'next_snapshot_matches_database', 'state_clean'] as $flag) {
            self::assertTrue($result[$flag], $name.' '.$flag);
        }
        self::assertFalse($result['transaction_active']);
        self::assertSame($name, $result['session_owner']);
        self::assertSame($link, $result['intent_clean']);
        self::assertSame(!$link, $result['pending_intent_preserved']);
        if ($link) {
            self::assertTrue($result['replay_denied']);
        }
    }

    private function event(string $name, string $expected): array
    {
        $read = [$this->workers[$name]['pipes'][1]];
        $write = $except = [];
        self::assertSame(1, stream_select($read, $write, $except, 25), 'Worker deadline: '.$name.' '.$expected);
        $line = fgets($read[0]);
        self::assertNotFalse($line, 'Worker output closed.');
        $event = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if ('error' === $event['event']) {
            self::fail('Worker failed: '.$event['type']);
        }
        self::assertSame($expected, $event['event'], $name.' unexpected worker event');

        return $event;
    }

    private function release(string $name): void
    {
        fwrite($this->workers[$name]['pipes'][0], "continue\n");
        fflush($this->workers[$name]['pipes'][0]);
    }

    private function awaitBlockedBy(int $waiter, int $holder, string $queryFragment): void
    {
        $deadline = microtime(true) + 10;
        do {
            if (1 === (int) $this->observer->fetchOne("SELECT COUNT(*) FROM pg_stat_activity WHERE pid = ? AND wait_event_type = 'Lock' AND ? = ANY(pg_blocking_pids(pid)) AND query LIKE ?", [$waiter, $holder, '%'.$queryFragment.'%'])) {
                return;
            }
            // EN: Observe the actual PostgreSQL waiter; IPC polling never releases a worker based on elapsed time.
            // RU: Наблюдаем реальное ожидание PostgreSQL; опрос IPC не освобождает worker по истечении времени.
            $read = array_map(static fn (array $worker) => $worker['pipes'][1], $this->workers);
            $write = $except = [];
            self::assertSame(0, stream_select($read, $write, $except, 0, 20000), 'Worker completed before observable database contention.');
        } while (microtime(true) < $deadline);
        self::fail('PostgreSQL contention deadline exceeded.');
    }

    private function finishWorkers(): void
    {
        foreach ($this->workers as $name => $worker) {
            self::assertTrue('' === stream_get_contents($worker['pipes'][2]), 'Worker emitted diagnostics.');
            foreach ($worker['pipes'] as $pipe) {
                fclose($pipe);
            }
            self::assertSame(0, proc_close($worker['process']));
            unset($this->workers[$name]);
        }
    }
}
