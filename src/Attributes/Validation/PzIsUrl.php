<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * URL válida (`FILTER_VALIDATE_URL`). Null e string vazia são ignorados.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzIsUrl implements ValidationRule
{
    public function __construct(
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if (! is_string($value) || $value === '' || filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return null;
        }

        return Messages::make($this->message, 'url', $field);
    }
}
