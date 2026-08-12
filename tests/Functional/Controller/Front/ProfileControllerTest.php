<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use App\Entity\User;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use App\Utils\Mailer\Sender\UserRegisteredEmailSender;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportException;

#[Group(name: 'functional')]
final class ProfileControllerTest extends WebTestCase
{
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
            $client->request('GET', '/ru/profile/resending-verify-email-link');

            self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('.alert-success');
            self::assertSelectorExists('a[href="/ru/profile/resending-verify-email-link"]');
        } finally {
            $user->setIsVerified($wasVerified);
            $entityManager->flush();
        }
    }
}
