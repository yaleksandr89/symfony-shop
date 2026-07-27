<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\OAuth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OAuth\OAuthUserResolver;
use App\Security\UserChecker\DeletedUserChecker;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

#[Group(name: 'unit')]
class OAuthUserResolverTest extends TestCase
{
    #[DataProvider('providerCases')]
    public function testDeletedSocialIdMatchIsRejectedWithoutMutation(string $provider, string $socialIdField, string $externalId): void
    {
        $user = $this->userWithSocialIds();
        $user->setIsDeleted(true);
        $before = $this->socialIds($user);
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with([$socialIdField => $externalId])->willReturn($user);
        $checker = $this->rejectingChecker($user);
        $factoryCalls = 0;

        try {
            $this->resolver($repository, $checker)->resolve($provider, $externalId, 'deleted@example.test', static function () use (&$factoryCalls): User {
                ++$factoryCalls;

                return new User();
            });
            self::fail('A deleted user must be rejected.');
        } catch (CustomUserMessageAccountStatusException $exception) {
            self::assertSame('Invalid credentials.', $exception->getMessageKey());
        }

        self::assertSame(0, $factoryCalls);
        self::assertSame($before, $this->socialIds($user));
    }

    #[DataProvider('providerCases')]
    public function testDeletedEmailFallbackIsRejectedBeforeSocialIdMutation(string $provider, string $socialIdField, string $externalId): void
    {
        $user = $this->userWithSocialIds();
        $user->setIsDeleted(true);
        $before = $this->socialIds($user);
        $lookups = [];
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::exactly(2))->method('findOneBy')->willReturnCallback(static function (array $criteria) use (&$lookups, $socialIdField, $externalId, $user): ?User {
            $lookups[] = $criteria;

            if ([$socialIdField => $externalId] === $criteria) {
                return null;
            }

            return ['email' => 'deleted@example.test'] === $criteria ? $user : null;
        });
        $checker = $this->rejectingChecker($user);
        $factoryCalls = 0;

        try {
            $this->resolver($repository, $checker)->resolve($provider, $externalId, 'deleted@example.test', static function () use (&$factoryCalls): User {
                ++$factoryCalls;

                return new User();
            });
            self::fail('A deleted user must be rejected.');
        } catch (CustomUserMessageAccountStatusException $exception) {
            self::assertSame('Invalid credentials.', $exception->getMessageKey());
        }

        self::assertSame([[$socialIdField => $externalId], ['email' => 'deleted@example.test']], $lookups);
        self::assertSame(0, $factoryCalls);
        self::assertSame($before, $this->socialIds($user));
    }

    #[DataProvider('providerCases')]
    public function testActiveSocialIdMatchDoesNotRequireFlush(string $provider, string $socialIdField, string $externalId): void
    {
        $user = $this->userWithSocialIds();
        $this->setSocialId($user, $socialIdField, $externalId);
        $before = $this->socialIds($user);
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with([$socialIdField => $externalId])->willReturn($user);
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::once())->method('checkPreAuth')->with($user);
        $factoryCalls = 0;

        $resolution = $this->resolver($repository, $checker)->resolve($provider, $externalId, 'active@example.test', static function () use (&$factoryCalls): User {
            ++$factoryCalls;

            return new User();
        });

        self::assertSame($user, $resolution->user());
        self::assertFalse($resolution->isNewUser());
        self::assertFalse($resolution->requiresFlush());
        self::assertSame(0, $factoryCalls);
        self::assertSame($before, $this->socialIds($user));
    }

    #[DataProvider('providerCases')]
    public function testActiveEmailFallbackLinksOnlyProviderSocialId(string $provider, string $socialIdField, string $externalId): void
    {
        $user = $this->userWithSocialIds();
        $before = $this->socialIds($user);
        $lookups = [];
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::exactly(2))->method('findOneBy')->willReturnCallback(static function (array $criteria) use (&$lookups, $socialIdField, $externalId, $user): ?User {
            $lookups[] = $criteria;

            if ([$socialIdField => $externalId] === $criteria) {
                return null;
            }

            return ['email' => 'active@example.test'] === $criteria ? $user : null;
        });
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::once())->method('checkPreAuth')->with($user);
        $factoryCalls = 0;

        $resolution = $this->resolver($repository, $checker)->resolve($provider, $externalId, 'active@example.test', static function () use (&$factoryCalls): User {
            ++$factoryCalls;

            return new User();
        });

        $after = $this->socialIds($user);
        $before[$socialIdField] = $externalId;
        self::assertSame([[$socialIdField => $externalId], ['email' => 'active@example.test']], $lookups);
        self::assertSame($user, $resolution->user());
        self::assertFalse($resolution->isNewUser());
        self::assertTrue($resolution->requiresFlush());
        self::assertSame(0, $factoryCalls);
        self::assertSame($before, $after);
    }

    #[DataProvider('providerCases')]
    public function testNewUserIsReturnedAndRequiresFlush(string $provider, string $socialIdField, string $externalId): void
    {
        $lookups = [];
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::exactly(2))->method('findOneBy')->willReturnCallback(static function (array $criteria) use (&$lookups): ?User {
            $lookups[] = $criteria;

            return null;
        });
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');
        $user = new User();
        $factoryCalls = 0;

        $resolution = $this->resolver($repository, $checker)->resolve($provider, $externalId, 'new@example.test', static function () use (&$factoryCalls, $user): User {
            ++$factoryCalls;

            return $user;
        });

        self::assertSame([[$socialIdField => $externalId], ['email' => 'new@example.test']], $lookups);
        self::assertSame($user, $resolution->user());
        self::assertTrue($resolution->isNewUser());
        self::assertTrue($resolution->requiresFlush());
        self::assertSame(1, $factoryCalls);
    }

    public function testUnknownProviderDoesNotLookUpCheckOrMutate(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('findOneBy');
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');
        $user = $this->userWithSocialIds();
        $before = $this->socialIds($user);
        $factoryCalls = 0;

        $this->expectException(InvalidArgumentException::class);
        try {
            $this->resolver($repository, $checker)->resolve('unknown', 'external-id', 'unknown@example.test', static function () use (&$factoryCalls): User {
                ++$factoryCalls;

                return new User();
            });
        } finally {
            self::assertSame(0, $factoryCalls);
            self::assertSame($before, $this->socialIds($user));
        }
    }

    public function testResolverOnlyDependsOnRepositoryAndDeletedUserChecker(): void
    {
        $properties = (new \ReflectionClass(OAuthUserResolver::class))->getProperties();

        self::assertSame(['userRepository', 'deletedUserChecker'], array_map(static fn (\ReflectionProperty $property): string => $property->getName(), $properties));
    }

    public static function providerCases(): iterable
    {
        yield 'Google' => [OAuthUserResolver::PROVIDER_GOOGLE, 'googleId', 'google-external-id'];
        yield 'Yandex' => [OAuthUserResolver::PROVIDER_YANDEX, 'yandexId', 'yandex-external-id'];
        yield 'VK' => [OAuthUserResolver::PROVIDER_VKONTAKTE, 'vkontakteId', 'vkontakte-external-id'];
        yield 'GitHub EN' => [OAuthUserResolver::PROVIDER_GITHUB_EN, 'githubId', 'github-external-id'];
        yield 'GitHub RU' => [OAuthUserResolver::PROVIDER_GITHUB_RUS, 'githubId', 'github-external-id'];
    }

    private function resolver(UserRepository $repository, DeletedUserChecker $checker): OAuthUserResolver
    {
        return new OAuthUserResolver($repository, $checker);
    }

    private function rejectingChecker(User $user): DeletedUserChecker
    {
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker
            ->expects(self::once())
            ->method('checkPreAuth')
            ->with($user)
            ->willThrowException(new CustomUserMessageAccountStatusException('Invalid credentials.'));

        return $checker;
    }

    private function userWithSocialIds(): User
    {
        $user = new User();
        $user->setGoogleId('google-original');
        $user->setYandexId('yandex-original');
        $user->setVkontakteId('vkontakte-original');
        $user->setGithubId('github-original');

        return $user;
    }

    private function setSocialId(User $user, string $socialIdField, string $value): void
    {
        match ($socialIdField) {
            'googleId' => $user->setGoogleId($value),
            'yandexId' => $user->setYandexId($value),
            'vkontakteId' => $user->setVkontakteId($value),
            'githubId' => $user->setGithubId($value),
        };
    }

    /**
     * @return array<string, string|null>
     */
    private function socialIds(User $user): array
    {
        return [
            'googleId' => $user->getGoogleId(),
            'yandexId' => $user->getYandexId(),
            'vkontakteId' => $user->getVkontakteId(),
            'githubId' => $user->getGithubId(),
        ];
    }
}
