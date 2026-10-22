<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * O valor recebido (antes do cast) precisa ser string. Null é ignorado.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class IsString implements ValidationRule
{
    public function __construct(
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if ($raw === null || is_string($raw)) {
            return null;
        }

        return Messages::make($this->message, 'The :field field must be a string.', $field);
    }
}
