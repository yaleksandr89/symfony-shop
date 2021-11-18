<?php

declare(strict_types=1);

namespace App\Exception\Security;

use InvalidArgumentException;

class EmptyUserPlainPasswordException extends InvalidArgumentException
{
}