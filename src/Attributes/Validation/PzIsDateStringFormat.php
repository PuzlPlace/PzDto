<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

/**
 * Formatos aceitos por {@see PzIsDateString}.
 */
enum PzIsDateStringFormat
{
    /** `Y-m-d` */
    case DATE;
    /** `Y-m-d H:i:s` */
    case DATETIME;
    /** ISO 8601 (`2026-10-22T10:00:00-03:00`, `2026-10-22T10:00:00Z`, …) */
    case ISO8601;
    /** Qualquer coisa que `new DateTimeImmutable()` entenda */
    case FLEXIBLE;

    public function parse(string $value): ?\DateTimeImmutable
    {
        return match ($this) {
            self::DATE => self::exact('!Y-m-d', $value),
            self::DATETIME => self::exact('Y-m-d H:i:s', $value),
            self::ISO8601 => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', $value) === 1
                ? self::loose($value)
                : null,
            self::FLEXIBLE => self::loose($value),
        };
    }

    private static function exact(string $format, string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat($format, $value);
        if ($date === false || $date->format(ltrim($format, '!')) !== $value) {
            return null;
        }

        return $date;
    }

    private static function loose(string $value): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
