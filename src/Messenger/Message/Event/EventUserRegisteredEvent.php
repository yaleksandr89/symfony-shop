<?php

declare(strict_types=1);

namespace App\Messenger\Message\Event;

use App\Entity\User;

class EventUserRegisteredEvent
{
    /**
     * @var int
     */
    private $userId;

    /**
     * @param int $userId
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }
}