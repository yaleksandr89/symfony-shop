<?php

declare(strict_types=1);

namespace App\Form\Filter;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Spiriit\Bundle\FormFilterBundle\Filter\Condition\ConditionInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Query\QueryInterface;

final class ExclusiveDateRangeFilter
{
    /**
     * @param array{value?: array{left_date?: array{0?: mixed}, right_date?: array{0?: mixed}}} $values
     */
    public function __invoke(QueryInterface $filterQuery, string $field, array $values): ?ConditionInterface
    {
        $lower = $values['value']['left_date'][0] ?? null;
        $upper = $values['value']['right_date'][0] ?? null;

        if (!$lower instanceof DateTimeImmutable && !$upper instanceof DateTimeImmutable) {
            return null;
        }

        $parameterPrefix = 'date_range_'.trim((string) preg_replace('/[^a-zA-Z0-9_]+/', '_', $field), '_');
        $expressions = [];
        $parameters = [];

        if ($lower instanceof DateTimeImmutable) {
            $lowerParameter = $parameterPrefix.'_lower';
            $expressions[] = sprintf('%s >= :%s', $field, $lowerParameter);
            $parameters[$lowerParameter] = [$lower->setTime(0, 0, 0, 0), Types::DATETIME_IMMUTABLE];
        }

        if ($upper instanceof DateTimeImmutable) {
            $upperParameter = $parameterPrefix.'_upper_exclusive';
            $nextDay = $upper->setTime(0, 0, 0, 0)->modify('+1 day');
            $expressions[] = sprintf('%s < :%s', $field, $upperParameter);
            $parameters[$upperParameter] = [$nextDay, Types::DATETIME_IMMUTABLE];
        }

        return $filterQuery->createCondition(implode(' AND ', $expressions), $parameters);
    }
}
