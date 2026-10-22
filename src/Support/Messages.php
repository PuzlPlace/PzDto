<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

/**
 * Monta as mensagens de erro de conversão e validação.
 *
 * Placeholders `:field`, `:min`, `:max`… são substituídos pelos valores da regra.
 * Mensagens customizadas (`message:` nos atributos) passam pelo tradutor, quando
 * registrado com {@see \Puzl\PzDto\PzDto::translateMessagesUsing()}.
 *
 * @internal
 */
final class Messages
{
    /** @var (\Closure(string, array<string, scalar>): string)|null */
    private static ?\Closure $translator = null;

    /**
     * @param (callable(string, array<string, scalar>): string)|null $translator
     */
    public static function translateUsing(?callable $translator): void
    {
        self::$translator = $translator === null ? null : \Closure::fromCallable($translator);
    }

    /**
     * @param array<string, scalar> $replace
     */
    public static function make(?string $custom, string $default, string $field, array $replace = []): string
    {
        $replace = ['field' => $field] + $replace;

        if ($custom !== null && self::$translator !== null) {
            return (self::$translator)($custom, $replace);
        }

        $pairs = [];
        foreach ($replace as $key => $value) {
            $pairs[':'.$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        // Chaves maiores primeiro: ":min" não pode comer o começo de ":minimum".
        uksort($pairs, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return strtr($custom ?? $default, $pairs);
    }
}
