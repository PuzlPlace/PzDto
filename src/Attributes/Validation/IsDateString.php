<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Support\Messages;

/**
 * String de data no formato informado, opcionalmente dentro de `min`/`max`
 * (no mesmo formato). Null e string vazia são ignorados.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class IsDateString implements ValidationRule
{
    public function __construct(
        public readonly IsDateStringFormat $format = IsDateStringFormat::DATE,
        public readonly ?string $min = null,
        public readonly ?string $max = null,
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = $this->format->parse($value);
        if ($date === null) {
            return Messages::make($this->message, 'The :field field must be a valid date.', $field);
        }

        $replace = ['min' => $this->min ?? '', 'max' => $this->max ?? ''];
        if ($this->min !== null && $date < $this->bound($this->min)) {
            return Messages::make($this->message, 'The :field field must be a date after or equal to :min.', $field, $replace);
        }
        if ($this->max !== null && $date > $this->bound($this->max)) {
            return Messages::make($this->message, 'The :field field must be a date before or equal to :max.', $field, $replace);
        }

        return null;
    }

    private function bound(string $value): \DateTimeImmutable
    {
        return $this->format->parse($value)
            ?? throw new PzDtoException(sprintf('#[IsDateString] bound "%s" is not a valid %s date.', $value, $this->format->name));
    }
}
