<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Mailer;

use App\Utils\Mailer\DTO\MailerOptionModel;
use App\Utils\Mailer\EmailAssetResolver;
use App\Utils\Mailer\MailerSender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[Group(name: 'unit')]
final class MailerSenderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/mailer-sender-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->projectDir.'/assets/images/icons', 0777, true));
        self::assertTrue(mkdir($this->projectDir.'/public/build', 0777, true));
        self::assertNotFalse(file_put_contents(
            $this->projectDir.'/assets/images/icons/alexander-yurchenko-php-developer.png',
            'test-logo',
        ));
        self::assertNotFalse(file_put_contents($this->projectDir.'/public/build/email.css', '.email { color: #123; }'));
    }

    protected function tearDown(): void
    {
        foreach ([
            $this->projectDir.'/assets/images/icons/alexander-yurchenko-php-developer.png',
            $this->projectDir.'/public/build/email.css',
        ] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ([
            $this->projectDir.'/assets/images/icons',
            $this->projectDir.'/assets/images',
            $this->projectDir.'/assets',
            $this->projectDir.'/public/build',
            $this->projectDir.'/public',
            $this->projectDir,
        ] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    #[DataProvider('ccCases')]
    #[TestDox('Успешная отправка возвращает то же составленное письмо и сохраняет опциональный CC')]
    public function testSuccessfulSendReturnsSameComposedEmail(?string $cc): void
    {
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            static function (RawMessage $message) use (&$sent): void {
                $sent = $message;
            }
        );
        $logger = new RecordingLogger();

        $result = $this->sender($mailer, $logger)->sendTemplatedEmail($this->options($cc));

        self::assertSame($sent, $result);
        self::assertSame('recipient@example.test', $result->getTo()[0]->getAddress());
        self::assertSame('front/email/test.html.twig', $result->getHtmlTemplate());
        self::assertSame('context-value', $result->getContext()['custom']);
        self::assertSame('.email { color: #123; }', $result->getContext()['email_inline_css']);
        self::assertSame('cid:symfony-shop-logo@symfony-shop', $result->getContext()['email_logo_cid']);
        self::assertSame(null === $cc ? [] : [$cc], array_map(
            static fn ($address): string => $address->getAddress(),
            $result->getCc(),
        ));
        self::assertSame([], $logger->records);
    }

    /** @return iterable<string, array{?string}> */
    public static function ccCases(): iterable
    {
        yield 'without CC' => [null];
        yield 'with CC' => ['copy@example.test'];
    }

    #[TestDox('Успешный fallback не скрывает исходный transport-сбой и не раскрывает диагностику')]
    public function testPrimaryTransportFailureWithSuccessfulFallbackRethrowsOriginalSafely(): void
    {
        $primary = $this->sensitiveTransportFailure();
        $fallback = null;
        $attempt = 0;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willReturnCallback(
            static function (RawMessage $message) use (&$attempt, &$fallback, $primary): void {
                ++$attempt;
                if (1 === $attempt) {
                    throw $primary;
                }

                $fallback = $message;
            }
        );
        $logger = new RecordingLogger();

        try {
            $this->sender($mailer, $logger)->sendTemplatedEmail($this->options());
            self::fail('The primary transport failure must be rethrown.');
        } catch (TransportException $exception) {
            self::assertSame($primary, $exception);
        }

        self::assertSame(2, $attempt);
        self::assertInstanceOf(Email::class, $fallback);
        self::assertNotInstanceOf(TemplatedEmail::class, $fallback);
        self::assertSame('admin@example.test', $fallback->getTo()[0]->getAddress());
        self::assertSame(
            "Primary email delivery failed.\nException class: ".TransportException::class,
            $fallback->getTextBody(),
        );
        self::assertSame([[
            'critical',
            'Primary email delivery failed.',
            ['exception_class' => TransportException::class, 'mail_stage' => 'primary'],
        ]], $logger->records);
        $this->assertSensitiveDiagnosticsAbsent($logger, $fallback->getTextBody() ?? '');
    }

    #[TestDox('Сбой fallback не заменяет исходный transport-сбой и ограничивает отправку двумя попытками')]
    public function testPrimaryAndFallbackFailuresRethrowOriginalAfterExactlyTwoAttempts(): void
    {
        $primary = $this->sensitiveTransportFailure();
        $fallback = new \RuntimeException('fallback-credential must remain private');
        $attempt = 0;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willReturnCallback(
            static function () use (&$attempt, $primary, $fallback): void {
                ++$attempt;

                throw 1 === $attempt ? $primary : $fallback;
            }
        );
        $logger = new RecordingLogger();

        try {
            $this->sender($mailer, $logger)->sendTemplatedEmail($this->options());
            self::fail('The primary transport failure must be rethrown.');
        } catch (TransportException $exception) {
            self::assertSame($primary, $exception);
        }

        self::assertSame(2, $attempt);
        self::assertSame([
            [
                'critical',
                'Primary email delivery failed.',
                ['exception_class' => TransportException::class, 'mail_stage' => 'primary'],
            ],
            [
                'critical',
                'Fallback email delivery failed.',
                ['exception_class' => \RuntimeException::class, 'mail_stage' => 'fallback'],
            ],
        ], $logger->records);
        $this->assertSensitiveDiagnosticsAbsent($logger, '');
        self::assertStringNotContainsString('fallback-credential', serialize($logger->records));
    }

    #[TestDox('Не-transport сбой отправки пробрасывается без fallback и transport-логов')]
    public function testNonTransportPrimaryFailurePropagatesWithoutFallback(): void
    {
        $failure = new \RuntimeException('composition failure');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willThrowException($failure);
        $logger = new RecordingLogger();

        try {
            $this->sender($mailer, $logger)->sendTemplatedEmail($this->options());
            self::fail('The non-transport failure must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame([], $logger->records);
    }

    #[TestDox('Сбой разрешения ресурса останавливает отправку до transport boundary и fallback')]
    public function testAssetFailureOccursBeforeAnyTransportAttempt(): void
    {
        unlink($this->projectDir.'/assets/images/icons/alexander-yurchenko-php-developer.png');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $logger = new RecordingLogger();

        $this->expectException(\RuntimeException::class);

        try {
            $this->sender($mailer, $logger)->sendTemplatedEmail($this->options());
        } finally {
            self::assertSame([], $logger->records);
        }
    }

    private function sender(MailerInterface $mailer, RecordingLogger $logger): MailerSender
    {
        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnCallback(
            static function (string $name): string {
                self::assertSame('admin_email', $name);

                return 'admin@example.test';
            }
        );
        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturnCallback(
            static function (string $path): string {
                self::assertSame('build/email.css', $path);

                return '/build/email.css';
            }
        );
        $sender = new MailerSender(
            $parameterBag,
            new EmailAssetResolver($packages, $this->projectDir),
        );
        $sender->setMailer($mailer);
        $sender->setLogger($logger);

        return $sender;
    }

    private function options(?string $cc = null): MailerOptionModel
    {
        $options = (new MailerOptionModel())
            ->setRecipient('recipient@example.test')
            ->setSubject('Test subject')
            ->setHtmlTemplate('front/email/test.html.twig')
            ->setContext(['custom' => 'context-value']);

        if (null !== $cc) {
            $options->setCc($cc);
        }

        return $options;
    }

    private function sensitiveTransportFailure(): TransportException
    {
        try {
            throw new TransportException(implode(' ', [
                'recipient-private@example.test',
                'smtp-password-private',
                'verification-token-private',
                '/private/filesystem/path',
            ]));
        } catch (TransportException $exception) {
            $exception->appendDebug('smtp://private-debug');

            return $exception;
        }
    }

    private function assertSensitiveDiagnosticsAbsent(RecordingLogger $logger, string $fallbackBody): void
    {
        $diagnostics = serialize($logger->records).$fallbackBody;

        foreach ([
            'recipient-private@example.test',
            'smtp-password-private',
            'verification-token-private',
            '/private/filesystem/path',
            'smtp://private-debug',
            __FILE__,
        ] as $sensitiveValue) {
            self::assertStringNotContainsString($sensitiveValue, $diagnostics);
        }
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{mixed, string, array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}
