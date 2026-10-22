<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

/**
 * Falha ao converter o valor de uma propriedade.
 *
 * As chaves de `$errors` são relativas à propriedade: `''` é a própria
 * propriedade, `'0.qty'` é um campo de um item aninhado.
 *
 * @internal
 */
final class CastError extends \RuntimeException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct((string) (array_merge(...array_values($errors))[0] ?? 'Invalid value.'));
    }

    public static function message(string $message): self
    {
        return new self(['' => [$message]]);
    }
}
