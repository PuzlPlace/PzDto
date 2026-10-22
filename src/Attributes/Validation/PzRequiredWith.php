<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ContextualValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * Obrigatório quando qualquer um dos outros campos veio com valor.
 *
 * ```php
 * #[PzRequiredWith('street', 'city')]
 * public ?string $zip;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzRequiredWith implements ContextualValidationRule
{
    /** @var array<int, string> */
    public readonly array $others;

    public function __construct(string ...$others)
    {
        $this->others = array_values($others);
    }

    public function validate(mixed $value, mixed $raw, string $field, array $input): ?string
    {
        $present = array_filter($this->others, static fn (string $other): bool => ! PzRequired::isMissing($input[$other] ?? null));
        if ($present === [] || ! PzRequired::isMissing($raw)) {
            return null;
        }

        return Messages::make(null, 'required_with', $field, ['values' => implode(', ', $present)]);
    }
}
