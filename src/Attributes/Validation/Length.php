<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * Quantidade de caracteres (multibyte) de uma string. Não-string é ignorado.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Length implements ValidationRule
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $length = mb_strlen($value);
        $replace = ['min' => $this->min ?? 0, 'max' => $this->max ?? 0];

        if ($this->min !== null && $length < $this->min) {
            return Messages::make($this->message, 'The :field field must be at least :min characters.', $field, $replace);
        }
        if ($this->max !== null && $length > $this->max) {
            return Messages::make($this->message, 'The :field field must not be greater than :max characters.', $field, $replace);
        }

        return null;
    }
}
