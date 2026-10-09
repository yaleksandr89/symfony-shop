<?php

declare(strict_types=1);

namespace App\Tests\TestUtils\OAuth;

use App\Entity\User;
use App\Kernel;
use App\OAuthBundle\Security\OAuth\OAuthAccountLinker;
use App\OAuthBundle\Security\OAuth\OAuthIdentityAccessor;
use App\OAuthBundle\Security\OAuth\OAuthLinkIntentStore;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use App\OAuthBundle\Security\OAuth\OAuthProviderAvailability;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use LogicException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;
use Symfony\Component\Validator\Constraint;
use Throwable;

final class OAuthIdentityMutationPostgreSqlWorker
{
    public static function run(): void
    {
        $input = json_decode(self::readLine(), true, flags: JSON_THROW_ON_ERROR);
        $provider = OAuthProvider::from($input['provider']);
        $accessor = new OAuthIdentityAccessor();
        $kernel = new Kernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        $client = $container->get('test.client');
        $client->disableReboot();
        $client->catchExceptions(false);
        $manager = $container->get(EntityManagerInterface::class);
        $connection = $manager->getConnection();
        if ('oauth_identity_concurrency_test' !== $connection->fetchOne('SELECT current_database()')) {
            throw new LogicException('Disposable OAuth database required.');
        }
        $connection->executeStatement("SET lock_timeout = '15s'");
        $connection->executeStatement("SET statement_timeout = '20s'");
        $registry = $container->get('doctrine');
        $trackingRegistry = new class($kernel->getContainer(), $registry->getConnectionNames(), $registry->getManagerNames(), $registry->getDefaultConnectionName(), $registry->getDefaultManagerName()) extends Registry {
            public bool $closedObserved = false;
            public int $resets = 0;

            public function getManagerForClass(string $class): ?ObjectManager
            {
                $manager = parent::getManagerForClass($class);
                if ($manager instanceof EntityManagerInterface && !$manager->isOpen()) {
                    $this->closedObserved = true;
                }

                return $manager;
            }

            public function resetManager(?string $name = null): ObjectManager
            {
                ++$this->resets;

                return parent::resetManager($name);
            }
        };
        $container->set(OAuthAccountLinker::class, new OAuthAccountLinker($accessor, $trackingRegistry));
        $user = $manager->find(User::class, $input['user_id']);
        if (!$user instanceof User) {
            throw new RuntimeException('Synthetic user missing.');
        }
        $client->loginUser($user, 'website');
        $container->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            ['google' => true, 'github_en' => true, 'github_rus' => true],
            array_fill_keys(['google', 'github_en', 'github_rus'], ['clientId' => 'synthetic-id', 'clientSecret' => 'synthetic-secret']),
        ));
        $listener = new class($input, $provider, $accessor) {
            private bool $active = false;

            public function __construct(private array $input, private OAuthProvider $provider, private OAuthIdentityAccessor $accessor)
            {
            }

            public function preFlush(PreFlushEventArgs $args): void
            {
                $manager = $args->getObjectManager();
                $user = $manager->find(User::class, $this->input['user_id']);
                $desired = 'unlink' === $this->input['action'] ? null : $this->input['external_id'];
                if (!$user instanceof User || $desired !== $this->accessor->getExternalId($user, $this->provider)) {
                    return;
                }
                $this->active = true;
                $original = $manager->getUnitOfWork()->getOriginalEntityData($user);
                OAuthIdentityMutationPostgreSqlWorker::barrier('before_flush', [
                    'in_transaction' => $manager->getConnection()->isTransactionActive(),
                    'refreshed_matches_expected' => ($original[$this->accessor->identityField($this->provider)] ?? null) === $this->input['observed_id'],
                ]);
            }

            public function postFlush(PostFlushEventArgs $args): void
            {
                if (!$this->active) {
                    return;
                }
                $this->active = false;
                OAuthIdentityMutationPostgreSqlWorker::barrier('written', [
                    'in_transaction' => $args->getObjectManager()->getConnection()->isTransactionActive(),
                ]);
                if ($this->input['rollback']) {
                    throw new RuntimeException('Synthetic transaction rollback.');
                }
            }
        };
        if ('link' === $input['action']) {
            $owner = new class($input, $connection) implements ResourceOwnerInterface {
                public function __construct(private array $input, private Connection $connection)
                {
                }

                public function getId(): string
                {
                    OAuthIdentityMutationPostgreSqlWorker::barrier('preliminary', [
                        'in_transaction' => $this->connection->isTransactionActive(),
                    ]);

                    return $this->input['external_id'];
                }

                public function toArray(): array
                {
                    return [];
                }
            };
            $fake = new FakeOAuth2Client($container->get(RequestStack::class), $owner);
            $clients = new Container();
            $clients->set('fake.oauth', $fake);
            $container->set('knpu.oauth2.registry', new ClientRegistry($clients, [$provider->oauthClientName() => 'fake.oauth']));
            $session = $container->get('session.factory')->createSession();
            $session->setId($client->getCookieJar()->get($session->getName())->getValue());
            $requestStack = $container->get(RequestStack::class);
            $request = Request::create('/');
            $request->setSession($session);
            $requestStack->push($request);
            $container->get(OAuthLinkIntentStore::class)->store($user, $provider, 'fake-oauth-state');
            $session->set('knpu.oauth2_client_state', 'fake-oauth-state');
            $session->set('oauth_worker', $input['worker']);
            $session->save();
            $requestStack->pop();
            $callback = match ($provider) {
                OAuthProvider::GithubEn => '/ru/connect/github-en/check',
                OAuthProvider::GithubRus => '/ru/connect/github-ru/check',
                default => '/ru/connect/google/check',
            };
        } else {
            $validator = new class($container->get('security.token_storage'), $container->get('security.password_hasher_factory'), $connection) extends UserPasswordValidator {
                public function __construct(TokenStorageInterface $tokenStorage, PasswordHasherFactoryInterface $factory, private Connection $connection)
                {
                    parent::__construct($tokenStorage, $factory);
                }

                public function validate(mixed $password, Constraint $constraint): void
                {
                    $before = $this->context->getViolations()->count();
                    parent::validate($password, $constraint);
                    if ($before === $this->context->getViolations()->count()) {
                        OAuthIdentityMutationPostgreSqlWorker::barrier('preliminary', ['in_transaction' => $this->connection->isTransactionActive()]);
                    }
                }
            };
            $container->set('security.validator.user_password', $validator);
            $crawler = $client->request('GET', '/ru/profile/oauth/'.$provider->value.'/unlink');
            if (200 !== $client->getResponse()->getStatusCode()) {
                throw new RuntimeException('Unlink form missing.');
            }
            $form = $crawler->filter('form')->form(['oauth_unlink_form[currentPassword]' => 'synthetic-current-password']);
            $session = $client->getRequest()->getSession();
            $session->set('oauth_worker', $input['worker']);
            $session->set('oauth_link_intent', ['test-marker' => 'pending']);
            $session->save();
        }
        self::emit([
            'event' => 'ready',
            'pid' => (int) $connection->fetchOne('SELECT pg_backend_pid()'),
            'version' => (int) $connection->fetchOne('SHOW server_version_num'),
            'isolation' => $connection->fetchOne('SHOW transaction_isolation'),
            'outer_transaction' => $connection->isTransactionActive() || StaticDriver::isKeepStaticConnections(),
            'observed_matches' => $accessor->getExternalId($user, $provider) === $input['observed_id'],
        ]);
        $manager->getEventManager()->addEventListener([Events::preFlush, Events::postFlush], $listener);
        $originalUnitOfWork = $manager->getUnitOfWork();
        if ('link' === $input['action']) {
            $client->request('GET', $callback, ['code' => 'fake-code', 'state' => 'fake-oauth-state']);
        } else {
            $client->submit($form);
        }
        $manager = $container->get(EntityManagerInterface::class);
        $manager->getEventManager()->removeEventListener([Events::preFlush, Events::postFlush], $listener);
        $session = $client->getRequest()->getSession();
        $token = $container->get('security.token_storage')->getToken();
        $tokenUser = $token?->getUser();
        $column = $manager->getClassMetadata(User::class)->getColumnName($accessor->identityField($provider));
        $snapshot = [
            'event' => 'result',
            'outcome' => $session->getFlashBag()->has('success') ? 'success' : 'conflict',
            'redirect' => 302 === $client->getResponse()->getStatusCode() && '/ru/profile' === $client->getResponse()->headers->get('Location'),
            'generic_failure' => !$session->getFlashBag()->has('success') && [$container->get('translator')->trans('link' === $input['action'] ? 'personal_account.social_group.oauth_link.failure' : 'Denied')] === $session->getFlashBag()->peek('danger'),
            'closed_observed' => $trackingRegistry->closedObserved,
            'resets' => $trackingRegistry->resets,
            'unit_of_work_replaced' => $originalUnitOfWork !== $manager->getUnitOfWork(),
            'manager_open' => $manager->isOpen(),
            'transaction_active' => $connection->isTransactionActive(),
            'same_account' => $tokenUser instanceof User && $tokenUser->getId() === $input['user_id'],
            'managed_snapshot' => $tokenUser instanceof User && $manager->contains($tokenUser),
            'snapshot_matches_database' => $tokenUser instanceof User && $accessor->getExternalId($tokenUser, $provider) === $connection->fetchOne('SELECT '.$column.' FROM "user" WHERE id = ?', [$input['user_id']]),
            'intent_clean' => !$session->has('oauth_link_intent'),
            'pending_intent_preserved' => ['test-marker' => 'pending'] === $session->get('oauth_link_intent'),
            'state_clean' => !$session->has('knpu.oauth2_client_state'),
            'session_owner' => $session->get('oauth_worker'),
        ];
        $roles = $token?->getRoleNames();
        $client->request('GET', '/ru/profile');
        $nextUser = $container->get('security.token_storage')->getToken()?->getUser();
        $snapshot['next_authenticated'] = 200 === $client->getResponse()->getStatusCode() && $nextUser instanceof User && $nextUser->getId() === $input['user_id'];
        $snapshot['roles_unchanged'] = $roles === $container->get('security.token_storage')->getToken()?->getRoleNames();
        $snapshot['next_snapshot_matches_database'] = $nextUser instanceof User && $accessor->getExternalId($nextUser, $provider) === $connection->fetchOne('SELECT '.$column.' FROM "user" WHERE id = ?', [$input['user_id']]);
        if ('link' === $input['action']) {
            $client->catchExceptions(true);
            $client->request('GET', $callback, ['code' => 'fake-code', 'state' => 'fake-oauth-state']);
            $snapshot['replay_denied'] = 403 === $client->getResponse()->getStatusCode() && 1 === $fake->tokenRequests && 1 === $fake->userInfoRequests;
        }
        self::emit($snapshot);
        $kernel->shutdown();
    }

    public static function barrier(string $event, array $details = []): void
    {
        self::emit(['event' => $event] + $details);
        if ('continue' !== self::readLine()) {
            throw new RuntimeException('Invalid barrier command.');
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
            throw new RuntimeException('Worker barrier deadline exceeded.');
        }
        $line = fgets(STDIN);
        if (false === $line) {
            throw new RuntimeException('Worker input closed.');
        }

        return trim($line);
    }
}

if (__FILE__ === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require dirname(__DIR__, 2).'/bootstrap.php';
    try {
        OAuthIdentityMutationPostgreSqlWorker::run();
    } catch (Throwable $exception) {
        // EN: Never emit exception messages, requests, sessions or connection parameters.
        // RU: Не выводим сообщения исключений, запросы, сессии и параметры соединения.
        fwrite(STDOUT, json_encode(['event' => 'error', 'type' => $exception::class], JSON_THROW_ON_ERROR)."\n");
        exit(1);
    }
}
