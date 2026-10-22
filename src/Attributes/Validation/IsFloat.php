<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * O valor recebido (antes do cast) precisa ser numérico: `1`, `1.5`, `"1.5"` e
 * `"1,5"` passam; `"1.5abc"` falha. Null é ignorado.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class IsFloat implements ValidationRule
{
    public function __construct(
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if ($raw === null || is_int($raw) || is_float($raw)) {
            return null;
        }
        if (is_string($raw) && (is_numeric(trim($raw)) || preg_match('/^\s*[+-]?\d+,\d+\s*$/', $raw) === 1)) {
            return null;
        }

        return Messages::make($this->message, 'The :field field must be a number.', $field);
    }
}
