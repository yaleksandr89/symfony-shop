<?php

namespace App\Tests\Unit\Utils\Generator;

use App\Utils\Generator\PasswordGenerator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
class PasswordGeneratorTest extends TestCase
{
    public function testGeneratorPassword(): void
    {
        $password = PasswordGenerator::generatePassword(20);

        self::assertSame(20, strlen($password));
    }
}
