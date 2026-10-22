<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

/**
 * Grandeza comparada por Min/Max: número → o próprio valor; string → quantidade
 * de caracteres; array → quantidade de itens.
 *
 * @internal
 */
final class Size
{
    public static function of(mixed $value): int|float|null
    {
        return match (true) {
            is_int($value), is_float($value) => $value,
            is_string($value) => mb_strlen($value),
            is_array($value) => count($value),
            $value instanceof \Countable => count($value),
            default => null,
        };
    }

    public static function unit(mixed $value): string
    {
        return match (true) {
            is_string($value) => ' characters',
            is_array($value), $value instanceof \Countable => ' items',
            default => '',
        };
    }
}
