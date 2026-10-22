<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * A chave precisa vir com valor: falha para ausente, null, string vazia/em branco ou array vazio.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzRequired implements ValidationRule
{
    public function __construct(
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        return self::isMissing($raw) ? Messages::make($this->message, 'required', $field) : null;
    }

    /**
     * Critério de "não veio" compartilhado com PzRequiredIf/PzRequiredWith.
     */
    public static function isMissing(mixed $raw): bool
    {
        return $raw === null
            || (is_string($raw) && trim($raw) === '')
            || $raw === [];
    }
}
