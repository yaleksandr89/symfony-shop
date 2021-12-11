<?php

declare(strict_types=1);

namespace App\Entity\StaticStorage;

final class OrderStaticStorage
{
    public const ORDER_STATUS_CREATED = 0;
    public const ORDER_STATUS_PROCESSED = 1;
    public const ORDER_STATUS_COMPLECTED = 2;
    public const ORDER_STATUS_DELIVERED = 3;
    public const ORDER_STATUS_DENIED = 4;

    /**
     * @return string[]
     */
    public static function getOrderStatusChoices(): array
    {
        return [
            self::ORDER_STATUS_CREATED => 'Created',
            self::ORDER_STATUS_PROCESSED => 'Processed',
            self::ORDER_STATUS_COMPLECTED => 'Complected',
            self::ORDER_STATUS_DELIVERED => 'Delivered',
            self::ORDER_STATUS_DENIED => 'Denied',
        ];
    }
}
