<?php

declare(strict_types=1);

namespace App\Account\Exception;

use InvalidArgumentException;

class EmptyUserPlainPasswordException extends InvalidArgumentException
{
}
