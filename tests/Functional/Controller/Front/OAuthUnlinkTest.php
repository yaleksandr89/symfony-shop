<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use App\Entity\User;
use App\Security\OAuth\OAuthProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group(name: 'functional')]
final class OAuthUnlinkTest extends WebTestCase
{
    private const PASSWORD = 'current-password';

    public function testUnauthenticatedRequestsDoNotUnlinkAnIdentity(): void
    {
        $client = self::createClient();
        $user = $this->createUser(['google' => 'linked-google']);

        $client->request('GET', '/ru/profile/oauth/google/unlink');
        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertIdentity($user, 'google', 'linked-google');

        $client->request('POST', '/ru/profile/oauth/google/unlink', [
            'oauth_unlink_form' => ['currentPassword' => self::PASSWORD],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertIdentity($user, 'google', 'linked-google');
        self::assertEmailCount(0);
    }

    public function testGetRendersAConfirmationFormWithoutRenderingOrChangingTheExternalId(): void
    {
        $externalId = 'sensitive-google-id';
        $client = self::createClient();
        $user = $this->createUser(['google' => $externalId]);
        $client->loginUser($user, 'website');

        $client->request('GET', '/ru/profile/oauth/google/unlink');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSelectorExists('form[method="post"]');
        self::assertSelectorExists('input[name="oauth_unlink_form[_token]"]');
        self::assertSelectorExists('input[name="oauth_unlink_form[currentPassword]"][type="password"]');
        self::assertStringNotContainsString($externalId, (string) $client->getResponse()->getContent());
        $this->assertIdentity($user, 'google', $externalId);
        self::assertEmailCount(0);
    }

    #[DataProvider('invalidCsrfSubmissions')]
    public function testMissingOrInvalidCsrfDoesNotUnlink(?string $csrfToken): void
    {
        $client = self::createClient();
        $user = $this->createUser(['google' => 'linked-google']);
        $client->loginUser($user, 'website');
        $form = ['currentPassword' => self::PASSWORD];
        if (null !== $csrfToken) {
            $form['_token'] = $csrfToken;
        }

        $client->request('POST', '/ru/profile/oauth/google/unlink', ['oauth_unlink_form' => $form]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertIdentity($user, 'google', 'linked-google');
        self::assertEmailCount(0);
    }

    public function testBlankPasswordDoesNotUnlink(): void
    {
        $client = self::createClient();
        $user = $this->createUser(['google' => 'linked-google']);
        $client->loginUser($user, 'website');
        $crawler = $client->request('GET', '/ru/profile/oauth/google/unlink');
        $form = $crawler->filter('form')->form([
            'oauth_unlink_form[currentPassword]' => '',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertIdentity($user, 'google', 'linked-google');
        self::assertEmailCount(0);
    }

    public function testWrongPasswordDoesNotUnlink(): void
    {
        $client = self::createClient();
        $user = $this->createUser(['google' => 'linked-google']);
        $client->loginUser($user, 'website');
        $crawler = $client->request('GET', '/ru/profile/oauth/google/unlink');
        $form = $crawler->filter('form')->form([
            'oauth_unlink_form[currentPassword]' => 'wrong-password',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertIdentity($user, 'google', 'linked-google');
        self::assertEmailCount(0);
    }

    #[DataProvider('providers')]
    public function testValidCurrentPasswordUnlinksOnlyTheSelectedIdentity(OAuthProvider $provider): void
    {
        $identities = [
            'google' => 'google-'.$provider->value,
            'yandex' => 'yandex-'.$provider->value,
            'vkontakte' => 'vkontakte-'.$provider->value,
            'github' => 'github-'.$provider->value,
        ];
        $client = self::createClient();
        $user = $this->createUser($identities);
        $client->loginUser($user, 'website');
        $url = '/ru/profile/oauth/'.$provider->value.'/unlink';
        $crawler = $client->request('GET', $url);
        $form = $crawler->filter('form')->form([
            'oauth_unlink_form[currentPassword]' => self::PASSWORD,
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);
        $client->followRedirect();
        self::assertSelectorExists('.alert-success');
        $identities[$provider->identityFamily()] = null;
        self::assertSame($identities, $this->identities($user));
        self::assertEmailCount(0);
    }

    public function testDisabledButLinkedProviderCanBeUnlinked(): void
    {
        $client = self::createClient();
        $user = $this->createUser(['google' => 'disabled-google']);
        $client->loginUser($user, 'website');
        $crawler = $client->request('GET', '/ru/profile/oauth/google/unlink');
        $form = $crawler->filter('form')->form([
            'oauth_unlink_form[currentPassword]' => self::PASSWORD,
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);
        $this->assertIdentity($user, 'google', null);
        self::assertEmailCount(0);
    }

    public function testFutureAndUnknownProvidersAndUnlinkedIdentityAreNotFound(): void
    {
        $client = self::createClient();
        $user = $this->createUser(['yandex' => 'linked-yandex']);
        $client->loginUser($user, 'website');

        foreach (['facebook', 'not-a-provider', 'google'] as $provider) {
            $client->request('GET', '/ru/profile/oauth/'.$provider.'/unlink');
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        $this->assertIdentity($user, 'yandex', 'linked-yandex');
        self::assertEmailCount(0);
    }

    public function testLegacyGetUrlIsNotFoundAndDoesNotUnlink(): void
    {
        $client = self::createClient();
        $user = $this->createUser(['google' => 'linked-google']);
        $client->loginUser($user, 'website');

        $client->request('GET', '/ru/profile/unlink_social_network/google');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertIdentity($user, 'google', 'linked-google');
        self::assertEmailCount(0);
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidCsrfSubmissions(): iterable
    {
        yield 'missing token' => [null];
        yield 'invalid token' => ['invalid-token'];
    }

    /** @return iterable<string, array{OAuthProvider}> */
    public static function providers(): iterable
    {
        yield 'Google' => [OAuthProvider::Google];
        yield 'Yandex' => [OAuthProvider::Yandex];
        yield 'Vkontakte' => [OAuthProvider::Vkontakte];
        yield 'Github EN' => [OAuthProvider::GithubEn];
        yield 'Github RU' => [OAuthProvider::GithubRus];
    }

    /** @param array<string, string> $linkedIdentities */
    private function createUser(array $linkedIdentities = []): User
    {
        $nonce = str_replace('.', '', uniqid('', true));
        $user = new User();
        $user
            ->setEmail('oauth-unlink-'.$nonce.'@example.test')
            ->setIsVerified(true);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $user->setGoogleId($linkedIdentities['google'] ?? null);
        $user->setYandexId($linkedIdentities['yandex'] ?? null);
        $user->setVkontakteId($linkedIdentities['vkontakte'] ?? null);
        $user->setGithubId($linkedIdentities['github'] ?? null);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    /** @return array<string, ?string> */
    private function identities(User $user): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloadedUser = $entityManager->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $reloadedUser);

        return [
            'google' => $reloadedUser->getGoogleId(),
            'yandex' => $reloadedUser->getYandexId(),
            'vkontakte' => $reloadedUser->getVkontakteId(),
            'github' => $reloadedUser->getGithubId(),
        ];
    }

    private function assertIdentity(User $user, string $provider, ?string $expectedIdentity): void
    {
        self::assertSame($expectedIdentity, $this->identities($user)[$provider]);
    }
}
