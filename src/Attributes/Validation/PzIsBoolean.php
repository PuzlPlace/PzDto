<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * O valor recebido (antes do cast) precisa representar booleano:
 * `true`, `false`, `0`, `1`, `"0"`, `"1"`, `"true"`, `"false"`. Null é ignorado.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzIsBoolean implements ValidationRule
{
    public function __construct(
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if ($raw === null || is_bool($raw) || in_array($raw, [0, 1, '0', '1', 'true', 'false'], true)) {
            return null;
        }

        return Messages::make($this->message, 'boolean', $field);
    }
}
