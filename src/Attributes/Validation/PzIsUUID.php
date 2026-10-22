<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * UUID (versões 1 a 8, mais o nil `0000…` e o max `ffff…`, como a regra `uuid`
 * do Laravel). Null e string vazia são ignorados.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzIsUUID implements ValidationRule
{
    private const PATTERN = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}|0{8}-0{4}-0{4}-0{4}-0{12}|f{8}-f{4}-f{4}-f{4}-f{12})$/i';

    public function __construct(
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if (! is_string($value) || $value === '' || preg_match(self::PATTERN, $value) === 1) {
            return null;
        }

        return Messages::make($this->message, 'uuid', $field);
    }
}
