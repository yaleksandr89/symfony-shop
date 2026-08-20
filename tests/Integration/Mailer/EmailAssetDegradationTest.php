<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mailer;

use App\Mailer\DTO\MailerOptionModel;
use App\Mailer\EmailAssetResolver;
use App\Mailer\MailerSender;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Group(name: 'integration')]
final class EmailAssetDegradationTest extends KernelTestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/email-asset-degradation-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->projectDir));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            rmdir($this->projectDir);
        }

        parent::tearDown();
    }

    #[TestDox('Письмо без CSS и логотипа рендерится и попадает в test transport без broken CID')]
    public function testRenderedEmailIsSentWhenBothDecorativeAssetsAreUnavailable(): void
    {
        self::bootKernel();
        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturnCallback(
            static function (string $path): string {
                self::assertSame('build/email.css', $path);

                return '/build/email.css';
            },
        );
        $mailer = self::getContainer()->get(MailerInterface::class);
        self::assertInstanceOf(MailerInterface::class, $mailer);
        $sender = new MailerSender(
            new ParameterBag(['admin_email' => 'admin@example.test']),
            new EmailAssetResolver($packages, $this->projectDir),
        );
        $sender->setMailer($mailer);
        $sender->setLogger(new NullLogger());

        $sender->sendTemplatedEmail(
            (new MailerOptionModel())
                ->setRecipient('recipient@example.test')
                ->setSubject('Decorative assets unavailable')
                ->setHtmlTemplate('email/base.html.twig'),
        );

        self::assertEmailCount(1);
        $message = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $message);
        $html = $message->getHtmlBody();
        self::assertNotNull($html);
        self::assertStringContainsString('By Symfony shop (pet projects)', $html);
        self::assertStringNotContainsString('Alexander Yurchenko', $html);
        self::assertStringNotContainsString('symfony-shop-logo@symfony-shop', $html);
        self::assertStringNotContainsString('src=""', $html);
        self::assertSame([], $message->getAttachments());
    }
}
