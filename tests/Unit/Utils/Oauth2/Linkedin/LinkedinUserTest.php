<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Oauth2\Linkedin;

use App\Utils\Oauth2\Linkedin\LinkedinUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
final class LinkedinUserTest extends TestCase
{
    public function testExposesCaseSensitiveSubjectProfileAndOriginalResponse(): void
    {
        $response = [
            'sub' => 'LiNkEdIn-Sub',
            'name' => 'LinkedIn User',
            'email' => 'user@example.test',
            'email_verified' => true,
        ];
        $user = new LinkedinUser($response);

        self::assertSame('LiNkEdIn-Sub', $user->getId());
        self::assertSame('LinkedIn User', $user->getName());
        self::assertSame('user@example.test', $user->getEmail());
        self::assertSame($response, $user->toArray());
    }

    public function testEmailAndInvalidOptionalProfileValuesAreNullable(): void
    {
        self::assertNull((new LinkedinUser(['sub' => 'subject']))->getEmail());
        self::assertNull((new LinkedinUser(['sub' => 'subject', 'name' => [], 'email' => new \stdClass()]))->getName());
        self::assertNull((new LinkedinUser(['sub' => 'subject', 'name' => [], 'email' => new \stdClass()]))->getEmail());
    }

    public function testStringableSubjectIsTrimmedWithoutCaseNormalization(): void
    {
        $subject = new class implements \Stringable {
            public function __toString(): string
            {
                return '  CaseSensitiveSubject  ';
            }
        };

        self::assertSame('CaseSensitiveSubject', (new LinkedinUser(['sub' => $subject]))->getId());
    }

    #[DataProvider('invalidSubjects')]
    public function testInvalidSubjectIsRejected(array $response): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('LinkedIn resource owner response does not contain a valid subject.');

        new LinkedinUser($response);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidSubjects(): iterable
    {
        yield 'missing' => [[]];
        yield 'blank' => [['sub' => '   ']];
        yield 'array' => [['sub' => ['subject']]];
        yield 'object' => [['sub' => new \stdClass()]];
    }
}
