<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;
use Puzl\PzDto\Support\Size;

/**
 * Máximo: valor numérico, tamanho da string ou quantidade de itens do array. Null é ignorado.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzMax implements ValidationRule
{
    public function __construct(
        public readonly int|float $value,
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        $size = Size::of($value);
        if ($size === null || $size <= $this->value) {
            return null;
        }

        return Messages::make(
            $this->message,
            'max.'.Size::kind($value),
            $field,
            ['max' => $this->value],
        );
    }
}
