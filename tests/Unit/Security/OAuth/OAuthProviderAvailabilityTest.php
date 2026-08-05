<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\OAuth;

use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthProviderAvailability;
use App\Security\OAuth\OAuthProviderConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OAuthProviderAvailabilityTest extends TestCase
{
    #[DataProvider('providers')]
    public function testEveryHardSwitchIsMappedIndependently(OAuthProvider $provider): void
    {
        $availability = $this->availability([$provider->value => true]);

        self::assertTrue($availability->isEnabled($provider));
        foreach (OAuthProvider::cases() as $otherProvider) {
            if ($otherProvider !== $provider) {
                self::assertFalse($availability->isEnabled($otherProvider));
            }
        }
    }

    public function testEnabledProvidersReturnsOnlyEnabledProviders(): void
    {
        $availability = $this->availability([
            OAuthProvider::Google->value => true,
            OAuthProvider::GithubRus->value => true,
        ]);

        self::assertSame(
            [OAuthProvider::Google, OAuthProvider::GithubRus],
            $availability->enabledProviders()
        );
    }

    public function testDisabledProviderIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->availability()->assertEnabled(OAuthProvider::Google);
    }

    #[DataProvider('implementedProviders')]
    public function testConfiguredEnabledImplementedProviderIsOperational(OAuthProvider $provider): void
    {
        $this->availability([$provider->value => true])->assertOperational($provider);

        self::assertTrue(true);
    }

    #[DataProvider('blankCredentialSets')]
    public function testEnabledImplementedProviderWithBlankCredentialsIsSanitized(array $credentials): void
    {
        $secret = 'not-for-error-output';
        $credentials[OAuthProvider::Yandex->value] = $credentials[OAuthProvider::Yandex->value] ?? [
            'clientId' => $secret,
            'clientSecret' => $secret,
        ];
        $availability = new OAuthProviderAvailability(
            [OAuthProvider::Yandex->value => true],
            $credentials
        );

        try {
            $availability->assertOperational(OAuthProvider::Yandex);
            self::fail('A blank credential must make an enabled provider unavailable.');
        } catch (OAuthProviderConfigurationException $exception) {
            self::assertSame('OAuth provider "yandex" is enabled but not configured.', $exception->getMessage());
            self::assertStringNotContainsString($secret, $exception->getMessage());
        }
    }

    #[DataProvider('futureProviders')]
    public function testFutureProviderHasNoClientConfigurationContract(OAuthProvider $provider): void
    {
        $availability = new OAuthProviderAvailability([$provider->value => true], []);

        $availability->assertOperational($provider);

        self::assertTrue($availability->isEnabled($provider));
    }

    /** @return iterable<string, array{OAuthProvider}> */
    public static function providers(): iterable
    {
        foreach (OAuthProvider::cases() as $provider) {
            yield $provider->value => [$provider];
        }
    }

    /** @return iterable<string, array{OAuthProvider}> */
    public static function implementedProviders(): iterable
    {
        foreach (OAuthProvider::cases() as $provider) {
            if ($provider->isImplemented()) {
                yield $provider->value => [$provider];
            }
        }
    }

    /** @return iterable<string, array{array<string, array{clientId: string, clientSecret: string}>}> */
    public static function blankCredentialSets(): iterable
    {
        yield 'blank client ID' => [[
            OAuthProvider::Yandex->value => ['clientId' => '   ', 'clientSecret' => 'not-for-error-output'],
        ]];
        yield 'blank client secret' => [[
            OAuthProvider::Yandex->value => ['clientId' => 'not-for-error-output', 'clientSecret' => "\t"],
        ]];
    }

    /** @return iterable<string, array{OAuthProvider}> */
    public static function futureProviders(): iterable
    {
        foreach (OAuthProvider::cases() as $provider) {
            if (!$provider->isImplemented()) {
                yield $provider->value => [$provider];
            }
        }
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
}
