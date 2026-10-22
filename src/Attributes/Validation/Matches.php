<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * String precisa casar com a regex (`preg_match`). Não-string é ignorado.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Matches implements ValidationRule
{
    public function __construct(
        public readonly string $pattern,
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if (! is_string($value) || preg_match($this->pattern, $value) === 1) {
            return null;
        }

        return Messages::make($this->message, 'The :field field format is invalid.', $field);
    }
}
