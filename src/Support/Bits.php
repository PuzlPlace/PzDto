<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

/**
 * Conjunto de posições guardado no menor espaço possível: um int (até 63
 * propriedades, custo zero de memória) ou uma string-bitmap acima disso.
 *
 * @internal
 */
final class Bits
{
    private const INT_BITS = PHP_INT_SIZE * 8 - 1;

    public static function fitsInInt(int $size): bool
    {
        return $size <= self::INT_BITS;
    }

    public static function empty(int $size): int|string
    {
        return self::fitsInInt($size) ? 0 : str_repeat("\0", intdiv($size + 7, 8));
    }

    public static function set(int|string $bits, int $position): int|string
    {
        if (is_int($bits)) {
            return $bits | (1 << $position);
        }

        $byte = intdiv($position, 8);
        $bits[$byte] = chr(ord($bits[$byte]) | (1 << ($position % 8)));

        return $bits;
    }

    public static function has(int|string $bits, int $position): bool
    {
        if (is_int($bits)) {
            return ($bits & (1 << $position)) !== 0;
        }

        return (ord($bits[intdiv($position, 8)]) & (1 << ($position % 8))) !== 0;
    }
}
