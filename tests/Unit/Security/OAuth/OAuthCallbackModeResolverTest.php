<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\OAuth;

use App\Entity\User;
use App\Security\OAuth\OAuthCallbackModeResolver;
use App\Security\OAuth\OAuthLinkIntentStore;
use App\Security\OAuth\OAuthProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

#[Group(name: 'unit')]
final class OAuthCallbackModeResolverTest extends TestCase
{
    #[DataProvider('modes')]
    #[TestDox('Режим callback зависит от аутентификации пользователя и ожидающего намерения')]
    public function testModeDependsOnAuthenticatedUserAndPendingIntent(bool $loggedIn, bool $pending, bool $ordinary): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $store = new OAuthLinkIntentStore($requestStack, new MockClock());
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 7);
        if ($pending) {
            $store->store($user, OAuthProvider::GithubRus, 'state');
        }

        $tokenStorage = new TokenStorage();
        if ($loggedIn) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'website', ['ROLE_USER']));
        }

        self::assertSame($ordinary, (new OAuthCallbackModeResolver($tokenStorage, $store))->useOrdinaryAuthenticator());
    }

    /** @return iterable<string, array{bool, bool, bool}> */
    public static function modes(): iterable
    {
        yield 'logged out, no intent' => [false, false, true];
        yield 'logged in, no intent' => [true, false, false];
        yield 'logged out, intent' => [false, true, false];
        yield 'logged in, intent' => [true, true, false];
    }
}
