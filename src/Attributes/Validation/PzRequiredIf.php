<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ContextualValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * Obrigatório quando outro campo do mesmo nível tem o valor informado
 * (comparação frouxa, como o `required_if` do Laravel: `'1'`, `1` e `true` casam).
 *
 * ```php
 * #[PzRequiredIf('type', 'company')]
 * public ?string $ein;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzRequiredIf implements ContextualValidationRule
{
    public function __construct(
        public readonly string $other,
        public readonly mixed $value = true,
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field, array $input): ?string
    {
        $otherValue = $input[$this->other] ?? null;
        if ($otherValue instanceof \BackedEnum) {
            $otherValue = $otherValue->value;
        }
        $expected = $this->value instanceof \BackedEnum ? $this->value->value : $this->value;

        if ($otherValue === null || $otherValue != $expected || ! PzRequired::isMissing($raw)) {
            return null;
        }

        return Messages::make($this->message, 'required_if', $field, [
            'other' => $this->other,
            'value' => is_bool($expected) ? ($expected ? 'true' : 'false') : (string) $expected,
        ]);
    }
}
