<?php

namespace GuzzleHttp\Command\Guzzle;

/**
 * Converts non-finite floats to the strings PHP coerces them to, as implicit
 * coercion of NAN emits a warning on PHP 8.5.
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
     *
     * @return mixed
     */
    public static function normalize($value)
    {
        if (is_float($value) && !is_finite($value)) {
            return is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF');
        }

        return $value;
    }

    /**
     * @return array
     */
    public static function normalizeAll(array $values)
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::normalizeAll($value);
            } else {
                $values[$key] = self::normalize($value);
            }
        }

        return $values;
    }
}
