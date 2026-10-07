<?php

declare(strict_types=1);

namespace App\Tests\Functional\Account\Controller;

use App\Account\Mailer\UserRegisteredEmailSender;
use App\Entity\User;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportException;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;

#[Group(name: 'functional')]
final class ProfileControllerTest extends WebTestCase
{
    #[TestDox('Корректная форма профиля сохраняет изменённые контактные данные')]
    public function testValidProfileEditPersistsSubmittedContactDetails(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $this->fixtureUser($entityManager);
        $userId = $user->getId();
        self::assertNotNull($userId);
        $original = $this->profileState($user);

        try {
            $client->loginUser($user, 'website');
            $crawler = $client->request('GET', '/ru/profile/edit');
            self::assertResponseIsSuccessful();

            $client->submit($crawler->filter('form[name="profile_edit_form"]')->form([
                'profile_edit_form[fullName]' => 'Updated Profile Name',
                'profile_edit_form[phone]' => '+7 999 111-22-33',
                'profile_edit_form[address]' => 'Updated profile address',
                'profile_edit_form[zipCode]' => '654321',
            ]));

            self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);
            $entityManager->clear();
            $persistedUser = $entityManager->find(User::class, $userId);
            self::assertInstanceOf(User::class, $persistedUser);
            self::assertSame('Updated Profile Name', $persistedUser->getFullName());
            self::assertSame('+7 999 111-22-33', $persistedUser->getPhone());
            self::assertSame('Updated profile address', $persistedUser->getAddress());
            self::assertSame(654321, $persistedUser->getZipCode());
        } finally {
            $entityManager->clear();
            $persistedUser = $entityManager->find(User::class, $userId);
            if ($persistedUser instanceof User) {
                $this->restoreProfileState($persistedUser, $original);
                $entityManager->flush();
            }
        }
    }

    #[TestDox('Некорректный индекс не сохраняет даже остальные привязанные изменения профиля')]
    public function testInvalidProfileEditDoesNotPersistBoundContactDetails(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $this->fixtureUser($entityManager);
        $userId = $user->getId();
        self::assertNotNull($userId);
        $original = $this->profileState($user);

        try {
            $client->loginUser($user, 'website');
            $crawler = $client->request('GET', '/ru/profile/edit');
            self::assertResponseIsSuccessful();

            $client->submit($crawler->filter('form[name="profile_edit_form"]')->form([
                'profile_edit_form[fullName]' => 'Must Not Persist',
                'profile_edit_form[phone]' => '+7 999 000-00-00',
                'profile_edit_form[address]' => 'Must not persist address',
                'profile_edit_form[zipCode]' => 'not-an-integer',
            ]));

            self::assertResponseIsSuccessful();
            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            $entityManager->clear();
            $persistedUser = $entityManager->find(User::class, $userId);
            self::assertInstanceOf(User::class, $persistedUser);
            self::assertSame($original, $this->profileState($persistedUser));
        } finally {
            $entityManager->clear();
            $persistedUser = $entityManager->find(User::class, $userId);
            if ($persistedUser instanceof User) {
                $this->restoreProfileState($persistedUser, $original);
                $entityManager->flush();
            }
        }
    }

    #[TestDox('Сбой повторной отправки не создаёт HTTP 500 и не показывает ложный успех')]
    public function testResendTransportFailureKeepsProfileFlowWithoutFalseSuccess(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'email' => UserFixtures::USER_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $user);
        $wasVerified = $user->isVerified();
        $user->setIsVerified(false);
        $entityManager->flush();

        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::once())->method('sendEmailToClient')
            ->willThrowException(new TransportException('delivery unavailable'));
        self::getContainer()->set(UserRegisteredEmailSender::class, $emailSender);

        try {
            $client->loginUser($user, 'website');
            $crawler = $client->request('GET', '/ru/profile');
            $client->submit($crawler->filter('form[action="/ru/profile/resending-verify-email-link"]')->form());

            self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('.alert-success');
            self::assertSelectorExists('form[action="/ru/profile/resending-verify-email-link"][method="post"]');
        } finally {
            $user->setIsVerified($wasVerified);
            $entityManager->flush();
        }
    }

    #[DataProvider('rejectedResendRequests')]
    #[TestDox('GET и POST без действительного CSRF не отправляют письмо подтверждения')]
    public function testResendRejectsUnsafeRequestsWithoutSendingEmail(string $method, array $parameters, int $status): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $this->fixtureUser($entityManager);
        $wasVerified = $user->isVerified();
        $user->setIsVerified(false);
        $entityManager->flush();
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::never())->method('sendEmailToClient');
        self::getContainer()->set(UserRegisteredEmailSender::class, $emailSender);

        try {
            $client->loginUser($user, 'website');
            $client->request($method, '/ru/profile/resending-verify-email-link', $parameters);
            self::assertResponseStatusCodeSame($status);
            $client->request('GET', '/ru/profile');
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('.alert-success');
        } finally {
            $user->setIsVerified($wasVerified);
            $entityManager->flush();
        }
    }

    /** @return iterable<string, array{string, array<string, string>, int}> */
    public static function rejectedResendRequests(): iterable
    {
        yield 'GET' => ['GET', [], Response::HTTP_METHOD_NOT_ALLOWED];
        yield 'missing' => ['POST', [], Response::HTTP_FORBIDDEN];
        yield 'invalid' => ['POST', ['_token' => 'invalid-csrf'], Response::HTTP_FORBIDDEN];
    }

    #[DataProvider('verificationStates')]
    #[TestDox('Защищённый POST отправляет подтверждение только непроверенному пользователю')]
    public function testValidResendPreservesVerificationContract(bool $verified): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $this->fixtureUser($entityManager);
        $wasVerified = $user->isVerified();
        $user->setIsVerified(false);
        $entityManager->flush();
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects($verified ? self::never() : self::once())->method('sendEmailToClient')
            ->with($user, self::isInstanceOf(VerifyEmailSignatureComponents::class));
        self::getContainer()->set(UserRegisteredEmailSender::class, $emailSender);

        try {
            $client->loginUser($user, 'website');
            $crawler = $client->request('GET', '/ru/profile');
            $form = $crawler->filter('form[action="/ru/profile/resending-verify-email-link"]')->form();
            if ($verified) {
                $user->setIsVerified(true);
                $entityManager->flush();
                $client->request('GET', '/ru/profile');
                self::assertSelectorNotExists('form[action="/ru/profile/resending-verify-email-link"]');
            }
            $client->submit($form);
            self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorCount($verified ? 0 : 1, '.alert-success');
            $client->request('GET', '/ru/profile');
            self::assertSelectorNotExists('.alert-success');
        } finally {
            $user->setIsVerified($wasVerified);
            $entityManager->flush();
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function verificationStates(): iterable
    {
        yield 'unverified' => [false];
        yield 'verified' => [true];
    }

    private function fixtureUser(EntityManagerInterface $entityManager): User
    {
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'email' => UserFixtures::USER_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    /** @return array{fullName: ?string, phone: ?string, address: ?string, zipCode: ?int} */
    private function profileState(User $user): array
    {
        return [
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'address' => $user->getAddress(),
            'zipCode' => $user->getZipCode(),
        ];
    }

    /** @param array{fullName: ?string, phone: ?string, address: ?string, zipCode: ?int} $state */
    private function restoreProfileState(User $user, array $state): void
    {
        $user
            ->setFullName($state['fullName'])
            ->setPhone($state['phone'])
            ->setAddress($state['address'])
            ->setZipCode($state['zipCode']);
    }
}
