<?php

declare(strict_types=1);

namespace App\Http\Traits;

/**
 * Shared helpers for backed enums. Expects the enum to implement
 * a `getData(): array` method returning `['id' => ..., 'name' => ..., 'slug' => ...]`.
 */
trait EnumHelpers
{
    /**
     * @return array<int, self>
     */
    public static function getAll(): array
    {
        return self::cases();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getAllWithData(): array
    {
        return array_map(
            static fn (self $case) => $case->getData(),
            self::cases(),
        );
    }

    /**
     * Find first case matching a predicate on its data array.
     */
    public static function firstWhere(string $key, mixed $value): ?self
    {
        foreach (self::cases() as $case) {
            if (($case->getData()[$key] ?? null) === $value) {
                return $case;
            }
        }

        return null;
    }
}
