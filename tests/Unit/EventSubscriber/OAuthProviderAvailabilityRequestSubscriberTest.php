<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\OAuthProviderAvailabilityRequestSubscriber;
use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthProviderAvailability;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[Group(name: 'unit')]
final class OAuthProviderAvailabilityRequestSubscriberTest extends TestCase
{
    #[DataProvider('currentRoutes')]
    #[TestDox('Отключённые сопоставленные маршруты возвращают 404')]
    public function testDisabledMappedRoutesAreNotFound(string $route, OAuthProvider $provider): void
    {
        $subscriber = new OAuthProviderAvailabilityRequestSubscriber($this->availability());

        $this->expectException(NotFoundHttpException::class);
        $subscriber->onKernelRequest($this->event($route));
    }

    #[DataProvider('currentRoutes')]
    #[TestDox('Настроенные включённые маршруты передаются дальше')]
    public function testConfiguredEnabledMappedRoutesPass(string $route, OAuthProvider $provider): void
    {
        $this->expectNotToPerformAssertions();
        $subscriber = new OAuthProviderAvailabilityRequestSubscriber(
            $this->availability([$provider->value => true])
        );

        $subscriber->onKernelRequest($this->event($route));

    }

    #[TestDox('Провайдер без учётных данных возвращает безопасную серверную ошибку')]
    public function testEnabledProviderWithMissingCredentialsReturnsSanitizedServerError(): void
    {
        $subscriber = new OAuthProviderAvailabilityRequestSubscriber(
            new OAuthProviderAvailability(
                [OAuthProvider::Yandex->value => true],
                [OAuthProvider::Yandex->value => ['clientId' => '', 'clientSecret' => '']]
            )
        );

        try {
            $subscriber->onKernelRequest($this->event('connect_yandex_start'));
            self::fail('Missing credentials must stop the route before the controller.');
        } catch (HttpException $exception) {
            self::assertSame(500, $exception->getStatusCode());
            self::assertSame('OAuth provider "yandex" is enabled but not configured.', $exception->getMessage());
        }
    }

    #[DataProvider('ignoredRoutes')]
    #[TestDox('Посторонние маршруты и маршруты отвязки игнорируются')]
    public function testUnrelatedAndUnlinkRoutesAreIgnored(string $route): void
    {
        $this->expectNotToPerformAssertions();
        (new OAuthProviderAvailabilityRequestSubscriber($this->availability()))
            ->onKernelRequest($this->event($route));

    }

    #[TestDox('Подзапрос игнорируется')]
    public function testSubrequestIsIgnored(): void
    {
        $this->expectNotToPerformAssertions();
        (new OAuthProviderAvailabilityRequestSubscriber($this->availability()))
            ->onKernelRequest($this->event('connect_google_check', HttpKernelInterface::SUB_REQUEST));

    }

    #[TestDox('Сопоставляются только текущие реализованные маршруты')]
    public function testOnlyCurrentImplementedRoutesAreMapped(): void
    {
        self::assertSame(OAuthProvider::Linkedin, OAuthProvider::fromRoute('connect_linkedin_check'));
        self::assertNull(OAuthProvider::fromRoute('connect_mailru_start'));
        $events = OAuthProviderAvailabilityRequestSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
        self::assertSame('onKernelRequest', $events[KernelEvents::REQUEST][0]);
    }

    /** @return iterable<string, array{string, OAuthProvider}> */
    public static function currentRoutes(): iterable
    {
        yield 'google start' => ['connect_google_start', OAuthProvider::Google];
        yield 'google callback' => ['connect_google_check', OAuthProvider::Google];
        yield 'yandex start' => ['connect_yandex_start', OAuthProvider::Yandex];
        yield 'yandex callback' => ['connect_yandex_check', OAuthProvider::Yandex];
        yield 'vkontakte start' => ['connect_vkontakte_start', OAuthProvider::Vkontakte];
        yield 'vkontakte callback' => ['connect_vkontakte_check', OAuthProvider::Vkontakte];
        yield 'GitHub EN start' => ['connect_github_en_start', OAuthProvider::GithubEn];
        yield 'GitHub EN callback' => ['connect_github_en_check', OAuthProvider::GithubEn];
        yield 'GitHub RU start' => ['connect_github_ru_start', OAuthProvider::GithubRus];
        yield 'GitHub RU callback' => ['connect_github_ru_check', OAuthProvider::GithubRus];
        yield 'Facebook start' => ['connect_facebook_start', OAuthProvider::Facebook];
        yield 'Facebook callback' => ['connect_facebook_check', OAuthProvider::Facebook];
        yield 'LinkedIn start' => ['connect_linkedin_start', OAuthProvider::Linkedin];
        yield 'LinkedIn callback' => ['connect_linkedin_check', OAuthProvider::Linkedin];
    }

    /** @return iterable<string, array{string}> */
    public static function ignoredRoutes(): iterable
    {
        yield 'unrelated route' => ['main_homepage'];
        yield 'unlink route' => ['main_profile_unlink_social_network'];
    }

    /** @param array<string, bool> $enabled */
    private function availability(array $enabled = []): OAuthProviderAvailability
    {
        $credentials = [];
        foreach (OAuthProvider::cases() as $provider) {
            if ($provider->isImplemented()) {
                $credentials[$provider->value] = [
                    'clientId' => $provider->value.'-id',
                    'clientSecret' => $provider->value.'-secret',
                ];
            }
        }

        return new OAuthProviderAvailability($enabled, $credentials);
    }

    private function event(string $route, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $requestType);
    }
}
