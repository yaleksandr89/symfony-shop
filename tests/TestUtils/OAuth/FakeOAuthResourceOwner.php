<?php

declare(strict_types=1);

namespace App\Tests\TestUtils\OAuth;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

final class FakeOAuthResourceOwner implements ResourceOwnerInterface
{
    public function __construct(private readonly mixed $id)
    {
    }

    public function getId(): mixed
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return ['id' => $this->id];
    }
}
