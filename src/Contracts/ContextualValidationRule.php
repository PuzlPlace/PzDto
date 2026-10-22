<?php

declare(strict_types=1);

namespace Puzl\PzDto\Contracts;

/**
 * Regra que precisa enxergar os outros campos do mesmo nível (ex.: "obrigatório
 * quando X veio", "fim depois do início").
 *
 * Mesmo contrato de {@see ValidationRule}, com o input bruto do DTO como quarto
 * argumento. Implemente uma OU outra interface, não as duas.
 */
interface ContextualValidationRule
{
    /**
     * @param mixed  $value Valor já convertido para o tipo da propriedade.
     * @param mixed  $raw   Valor bruto recebido (null quando a chave não veio).
     * @param string $field Nome da propriedade.
     * @param array<string, mixed> $input Todos os valores brutos recebidos neste nível
     *                                    (no `assertValid()`/`fill()`, o estado atual do DTO).
     *
     * @return string|null Mensagem de erro, ou null quando o valor é válido.
     */
    public function validate(mixed $value, mixed $raw, string $field, array $input): ?string;
}
