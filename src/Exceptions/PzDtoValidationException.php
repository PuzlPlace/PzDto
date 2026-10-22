<?php

declare(strict_types=1);

namespace Puzl\PzDto\Exceptions;

/**
 * Dados recebidos não passaram na conversão ou nas regras de validação.
 *
 * Reúne os erros de todas as propriedades (inclusive de DTOs aninhados, com chave
 * em notação de ponto: `address.zip`, `items.0.qty`).
 */
class PzDtoValidationException extends PzDtoException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(
        private readonly array $errors,
        private readonly string $dtoClass,
    ) {
        parent::__construct(self::summary($errors, $dtoClass));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function dtoClass(): string
    {
        return $this->dtoClass;
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    private static function summary(array $errors, string $dtoClass): string
    {
        $messages = array_merge(...array_values($errors));
        $first = $messages[0] ?? 'Invalid data.';
        $remaining = count($messages) - 1;

        $summary = $first;
        if ($remaining > 0) {
            $summary .= sprintf(' (and %d more %s)', $remaining, $remaining === 1 ? 'error' : 'errors');
        }

        return $summary.' ['.$dtoClass.']';
    }
}
