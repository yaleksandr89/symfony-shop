<?php

declare(strict_types=1);

namespace App\ApiPlatform\Error;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Error as ErrorOperation;
use ApiPlatform\Metadata\ErrorResource;
use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ErrorResource(
    operations: [
        new ErrorOperation(
            outputFormats: ['json' => ['application/problem+json']],
            normalizationContext: [
                'groups' => ['json'],
                'skip_null_values' => true,
                'rfc_7807_compliant_errors' => true,
            ],
            name: '_api_cart_products_unavailable_problem',
        ),
    ],
    status: 409,
    graphQlOperations: []
)]
final class CartProductsUnavailableException extends \RuntimeException implements ProblemExceptionInterface
{
    public const TYPE = '/problems/cart-products-unavailable';
    public const TITLE = 'Some products are no longer available';
    public const DETAIL = 'Remove unavailable products from the cart before checkout.';
    public const REASON_DELETED = 'deleted';
    public const REASON_UNPUBLISHED = 'unpublished';

    /** @var list<array{cartProductId: int, reason: 'deleted'|'unpublished'}> */
    private array $unavailableItems;

    /**
     * @param list<array<string, mixed>> $unavailableItems
     */
    public function __construct(array $unavailableItems)
    {
        if ([] === $unavailableItems || !array_is_list($unavailableItems)) {
            throw new \InvalidArgumentException('Unavailable cart products must be a non-empty list.');
        }

        $normalizedItems = [];
        foreach ($unavailableItems as $item) {
            $cartProductId = $item['cartProductId'] ?? null;
            $reason = $item['reason'] ?? null;
            if (!is_int($cartProductId) || $cartProductId <= 0) {
                throw new \InvalidArgumentException('Unavailable cart product IDs must be positive integers.');
            }

            if (!in_array($reason, [self::REASON_DELETED, self::REASON_UNPUBLISHED], true)) {
                throw new \InvalidArgumentException('Unavailable cart product reasons must be deleted or unpublished.');
            }

            if (isset($normalizedItems[$cartProductId])) {
                throw new \InvalidArgumentException('Unavailable cart product IDs must be unique.');
            }

            $normalizedItems[$cartProductId] = [
                'cartProductId' => $cartProductId,
                'reason' => $reason,
            ];
        }

        ksort($normalizedItems, SORT_NUMERIC);
        $this->unavailableItems = array_values($normalizedItems);

        parent::__construct(self::DETAIL);
    }

    #[Groups(['json'])]
    public function getType(): string
    {
        return self::TYPE;
    }

    #[Groups(['json'])]
    public function getTitle(): ?string
    {
        return self::TITLE;
    }

    #[Groups(['json'])]
    public function getStatus(): ?int
    {
        return 409;
    }

    #[Groups(['json'])]
    public function getDetail(): ?string
    {
        return self::DETAIL;
    }

    #[Groups(['json'])]
    public function getInstance(): ?string
    {
        return null;
    }

    /** @return list<array{cartProductId: int, reason: 'deleted'|'unpublished'}> */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'required' => ['cartProductId', 'reason'],
            'properties' => [
                'cartProductId' => ['type' => 'integer', 'minimum' => 1],
                'reason' => ['type' => 'string', 'enum' => [self::REASON_DELETED, self::REASON_UNPUBLISHED]],
            ],
        ],
    ])]
    #[Groups(['json'])]
    public function getUnavailableItems(): array
    {
        return $this->unavailableItems;
    }
}
