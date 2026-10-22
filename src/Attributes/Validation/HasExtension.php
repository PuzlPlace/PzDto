<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes\Validation;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Support\Messages;

/**
 * Nome/caminho de arquivo com extensão permitida. `allowed` aceita com ou sem
 * ponto (`['png', '.pdf']`); vazio = qualquer extensão, mas precisa ter uma.
 * Null e string vazia são ignorados.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class HasExtension implements ValidationRule
{
    /**
     * @param array<int, string> $allowed
     */
    public function __construct(
        public readonly array $allowed = [],
        public readonly ?string $message = null,
    ) {
    }

    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        if ($this->allowed === []) {
            return $extension === ''
                ? Messages::make($this->message, 'The :field field must have a file extension.', $field)
                : null;
        }

        $allowed = array_values(array_unique(array_map(
            static fn (string $item): string => ltrim(strtolower(trim($item)), '.'),
            $this->allowed,
        )));
        if ($extension !== '' && in_array($extension, $allowed, true)) {
            return null;
        }

        return Messages::make(
            $this->message,
            'The :field field must have one of the following extensions: :values.',
            $field,
            ['values' => implode(', ', array_map(static fn (string $item): string => '.'.$item, $allowed))],
        );
    }
}
