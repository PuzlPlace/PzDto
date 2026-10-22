<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * Valor precisa estar na lista. Enum é comparado pelo `value` (backed) ou `name`.
 * Em array, todos os itens precisam estar na lista. Null é ignorado.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzIn implements ValidationRule
{
    /**
     * @param array<int, mixed> $values
     */
    public function __construct(
        public readonly array $values,
        public readonly bool $strict = false,
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach (is_array($value) ? $value : [$value] as $item) {
            if (! in_array(self::scalar($item), array_map(self::scalar(...), $this->values), $this->strict)) {
                return Messages::make(
                    $this->message,
                    'in',
                    $field,
                    ['values' => implode(', ', array_map(static fn (mixed $v): string => (string) self::scalar($v), $this->values))],
                );
            }
        }

        return null;
    }

    private static function scalar(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            default => $value,
        };
    }
}
