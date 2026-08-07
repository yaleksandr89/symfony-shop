<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Oauth2\Facebook;

use App\Utils\Oauth2\Facebook\FacebookUser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
final class FacebookUserTest extends TestCase
{
    public function testExposesNormalizedIdentityOptionalProfileAndOriginalResponse(): void
    {
        $response = ['id' => 12345, 'name' => 'Facebook User', 'email' => 'user@example.test'];
        $user = new FacebookUser($response);

        self::assertSame('12345', $user->getId());
        self::assertSame('Facebook User', $user->getName());
        self::assertSame('user@example.test', $user->getEmail());
        self::assertSame($response, $user->toArray());
    }

    public function testEmailIsNullable(): void
    {
        self::assertNull((new FacebookUser(['id' => 'facebook-id']))->getEmail());
    }

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
