<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * Valor recebido não pode ser vazio no sentido do `empty()` do PHP
 * (null, '', '0', 0, 0.0, false, []).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzIsNotEmpty implements ValidationRule
{
    public function __construct(
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        return empty($raw) ? Messages::make($this->message, 'not_empty', $field) : null;
    }
}
