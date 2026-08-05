<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\OAuth;

use App\Entity\User;
use App\Security\OAuth\Exception\OAuthLinkIntentException;
use App\Security\OAuth\OAuthLinkIntentStore;
use App\Security\OAuth\OAuthProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[Group(name: 'unit')]
final class OAuthLinkIntentStoreTest extends TestCase
{
    private MockClock $clock;
    private Session $session;
    private OAuthLinkIntentStore $store;
    private User $user;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-08-06 12:00:00 UTC');
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->store = new OAuthLinkIntentStore($requestStack, $this->clock);
        $this->user = $this->user(41);
    }

    public function testStoresOnlyAStateHashAndConsumesOnce(): void
    {
        $rawState = 'sensitive-oauth-state';
        $this->store->store($this->user, OAuthProvider::Google, $rawState);

        $serialized = serialize($this->session->all());
        self::assertStringNotContainsString($rawState, $serialized);
        self::assertStringContainsString(hash('sha256', $rawState), $serialized);
        self::assertTrue($this->store->hasPending());

        $intent = $this->store->consume($this->user, OAuthProvider::Google, $rawState);

        self::assertSame(41, $intent->userId);
        self::assertSame(OAuthProvider::Google, $intent->provider);
        self::assertSame(hash('sha256', $rawState), $intent->stateHash);
        self::assertSame($this->clock->now()->getTimestamp(), $intent->issuedAt->getTimestamp());
        self::assertFalse($this->store->hasPending());

        $this->expectException(OAuthLinkIntentException::class);
        $this->store->consume($this->user, OAuthProvider::Google, $rawState);
    }

    #[DataProvider('invalidIntentCases')]
    public function testInvalidIntentIsRejectedAndConsumed(string $case): void
    {
        $state = 'correct-state';
        $provider = OAuthProvider::Yandex;
        $user = $this->user;

        if ('missing' !== $case && 'malformed' !== $case) {
            $this->store->store($user, $provider, $state);
        }
        if ('malformed' === $case) {
            $this->session->set('oauth_link_intent', ['bad' => 'data']);
        } elseif ('malformed-hash' === $case) {
            $this->session->set('oauth_link_intent', [
                'userId' => 41,
                'provider' => 'yandex',
                'stateHash' => 'not-a-sha256-hash',
                'issuedAt' => $this->clock->now()->getTimestamp(),
            ]);
        }
        if ('wrong-user' === $case) {
            $user = $this->user(99);
        } elseif ('wrong-provider' === $case) {
            $provider = OAuthProvider::Google;
        } elseif ('expired' === $case) {
            $this->clock->sleep(OAuthLinkIntentStore::TTL_SECONDS + 1);
        }

        $submittedState = match ($case) {
            'missing-state' => null,
            'blank-state' => '   ',
            'wrong-state' => 'wrong-state',
            default => $state,
        };

        try {
            $this->store->consume($user, $provider, $submittedState);
            self::fail('Invalid intent must be rejected.');
        } catch (OAuthLinkIntentException) {
            self::assertFalse($this->store->hasPending());
        }

        $this->expectException(OAuthLinkIntentException::class);
        $this->store->consume($this->user, OAuthProvider::Yandex, $state);
    }

    public function testTtlBoundaryIsAccepted(): void
    {
        $this->store->store($this->user, OAuthProvider::Vkontakte, 'state');
        $this->clock->sleep(OAuthLinkIntentStore::TTL_SECONDS);

        $intent = $this->store->consume($this->user, OAuthProvider::Vkontakte, 'state');

        self::assertSame(OAuthProvider::Vkontakte, $intent->provider);
    }

    public function testSecondFlowReplacesFirst(): void
    {
        $this->store->store($this->user, OAuthProvider::Google, 'old-state');
        $this->store->store($this->user, OAuthProvider::GithubEn, 'new-state');

        $this->expectException(OAuthLinkIntentException::class);
        $this->store->consume($this->user, OAuthProvider::Google, 'old-state');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidIntentCases(): iterable
    {
        foreach (['missing', 'malformed', 'malformed-hash', 'wrong-user', 'wrong-provider', 'missing-state', 'blank-state', 'wrong-state', 'expired'] as $case) {
            yield $case => [$case];
        }
    }

    private function user(int $id): User
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
