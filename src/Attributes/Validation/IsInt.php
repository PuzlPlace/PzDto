<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * O valor recebido (antes do cast) precisa ser inteiro: `5` ou `"5"` passam;
 * `"12abc"`, `"1.5"` e `1.5` falham. Null é ignorado.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class IsInt implements ValidationRule
{
    public function __construct(
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if ($raw === null || is_int($raw) || (is_string($raw) && preg_match('/^\s*[+-]?\d+\s*$/', $raw) === 1)) {
            return null;
        }

        return Messages::make($this->message, 'The :field field must be an integer.', $field);
    }
}
