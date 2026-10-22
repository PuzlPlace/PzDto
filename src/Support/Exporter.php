<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

use Puzl\PzDto\PzDto;

/**
 * Converte um valor para a forma "array puro" do toArray(), sem passar por
 * json_encode (que não aceita enum puro, string fora do UTF-8 nem INF/NAN):
 *
 * - DTO → `toArray()`; enum backed → `value`; enum puro → `name`;
 * - data → `Y-m-d H:i:s` no fuso do próprio objeto;
 * - JsonSerializable → o que ele serializa; outro objeto → propriedades públicas;
 * - array → item a item, preservando as chaves.
 *
 * @internal
 */
final class Exporter
{
    public const DATE_FORMAT = 'Y-m-d H:i:s';

    public static function value(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::value(...), $value);
        }
        if (! is_object($value)) {
            return $value;
        }

        return match (true) {
            $value instanceof PzDto => $value->toArray(),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof \DateTimeInterface => $value->format(self::DATE_FORMAT),
            $value instanceof \JsonSerializable => self::value($value->jsonSerialize()),
            default => self::value(get_object_vars($value)),
        };
    }
}
