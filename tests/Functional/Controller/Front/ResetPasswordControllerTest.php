<?php

namespace App\Tests\Functional\Controller\Front;

use App\Messenger\Message\Command\ResetUserPasswordCommand;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

#[Group(name: 'functional')]
class ResetPasswordControllerTest extends WebTestCase
{
    #[TestDox('Пустой email остаётся на форме и не ставит команду сброса в очередь')]
    public function testBlankEmailStaysOnFormWithoutQueueingResetCommand(): void
    {
        $client = static::createClient();

        $client->request('GET', '/ru/reset-password');
        $client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => '',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="reset_password_request_form"]');
        self::assertSelectorExists('input[name="reset_password_request_form[email]"]');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertCount(0, $transport->get());
    }

    #[DataProvider('knownAndUnknownEmails')]
    #[TestDox('Известный и неизвестный email получают нейтральный редирект и команду сброса')]
    public function testValidRequestQueuesCommandAndUsesNeutralRedirect(string $email): void
    {
        $client = static::createClient();

        $client->request('GET', '/ru/reset-password');
        $client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => $email,
        ]);

        self::assertResponseRedirects('/ru/reset-password/check-email', Response::HTTP_FOUND);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $envelopes = $transport->get();
        self::assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(ResetUserPasswordCommand::class, $message);
        self::assertSame($email, $message->getEmail());
    }

    /** @return iterable<string, array{string}> */
    public static function knownAndUnknownEmails(): iterable
    {
        yield 'known account' => [UserFixtures::USER_1_EMAIL];
        yield 'unknown account' => ['unknown-reset-request@example.test'];
    }
}
