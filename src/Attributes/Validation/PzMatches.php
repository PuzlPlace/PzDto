<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Support\Messages;

/**
 * String precisa casar com a regex (`preg_match`). Não-string é ignorado.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzMatches implements ValidationRule
{
    /**
     * @throws PzDtoException quando a regex não compila (erro de declaração, não de input)
     */
    public function __construct(
        public readonly string $pattern,
        public readonly ?string $message = null,
    ) {
        if (@preg_match($pattern, '') === false) {
            throw new PzDtoException(sprintf('#[PzMatches] pattern "%s" is not a valid regex: %s.', $pattern, preg_last_error_msg()));
        }
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if (! is_string($value) || preg_match($this->pattern, $value) === 1) {
            return null;
        }

        return Messages::make($this->message, 'regex', $field);
    }
}
