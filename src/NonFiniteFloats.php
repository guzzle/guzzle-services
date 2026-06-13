<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle;

/**
 * Rejects non-finite floats, which the request locations cannot serialize and
 * which emit coercion warnings on PHP 8.5.
 *
 * @internal
 */
final class NonFiniteFloats
{
    private function __construct()
    {
    }

    /**
     * @param mixed $value
     */
    public static function assertFinite($value, string $context): void
    {
        if (is_float($value) && !is_finite($value)) {
            throw new \InvalidArgumentException(sprintf('Non-finite floats are not supported for %s.', $context));
        }
    }

    public static function assertAllFinite(array $values, string $context): void
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                self::assertAllFinite($value, $context);
            } else {
                self::assertFinite($value, $context);
            }
        }
    }
}
