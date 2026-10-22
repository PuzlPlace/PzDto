<?php

declare(strict_types=1);

namespace Puzl\PzDto\Contracts;

/**
 * Regra de validação aplicada como atributo em propriedades de um PzDto.
 *
 * Para criar uma regra própria, implemente esta interface numa classe marcada com
 * `#[\Attribute(\Attribute::TARGET_PROPERTY)]`.
 */
interface ValidationRule
{
    /**
     * @param mixed  $value Valor já convertido para o tipo da propriedade.
     * @param mixed  $raw   Valor bruto recebido (null quando a chave não veio).
     * @param string $field Nome da propriedade.
     *
     * @return string|null Mensagem de erro, ou null quando o valor é válido.
     */
    public function validate(mixed $value, mixed $raw, string $field): ?string;
}
