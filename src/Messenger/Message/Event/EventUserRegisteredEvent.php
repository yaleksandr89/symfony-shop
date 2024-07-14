<?php

declare(strict_types=1);

namespace App\Messenger\Message\Event;

class EventUserRegisteredEvent
{
    public function __construct(private int $userId)
    {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
