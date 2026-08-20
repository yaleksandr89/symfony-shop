<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Provider\Facebook;

use App\OAuthBundle\Provider\Facebook\FacebookUser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
final class FacebookUserTest extends TestCase
{
    #[TestDox('Раскрываются нормализованный идентификатор, необязательный профиль и исходный ответ')]
    public function testExposesNormalizedIdentityOptionalProfileAndOriginalResponse(): void
    {
        $response = ['id' => 12345, 'name' => 'Facebook User', 'email' => 'user@example.test'];
        $user = new FacebookUser($response);

        self::assertSame('12345', $user->getId());
        self::assertSame('Facebook User', $user->getName());
        self::assertSame('user@example.test', $user->getEmail());
        self::assertSame($response, $user->toArray());
    }

    #[TestDox('Поле email допускает null')]
    public function testEmailIsNullable(): void
    {
        self::assertNull((new FacebookUser(['id' => 'facebook-id']))->getEmail());
    }

    #[TestDox('Отсутствующий или пустой ID отклоняется')]
    public function testMissingOrBlankIdIsRejected(): void
    {
        foreach ([[], ['id' => '   '], ['id' => []]] as $response) {
            try {
                new FacebookUser($response);
                self::fail('A Facebook resource owner without a valid ID must be rejected.');
            } catch (\UnexpectedValueException) {
                self::assertTrue(true);
            }
        }
    }
}
