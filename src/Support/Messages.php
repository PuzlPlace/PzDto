<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

use Puzl\PzDto\Exceptions\PzDtoException;

/**
 * Monta as mensagens de erro de conversão e validação.
 *
 * Cada mensagem padrão tem uma chave (`required`, `min.string`…) e um texto por
 * idioma. O idioma ativo é trocado com {@see \Puzl\PzDto\PzDto::useLocale()} e
 * textos pontuais com {@see \Puzl\PzDto\PzDto::useMessages()}.
 *
 * Placeholders `:field`, `:min`, `:max`… são substituídos pelos valores da regra.
 * Mensagens customizadas (`message:` nos atributos) passam pelo tradutor, quando
 * registrado com {@see \Puzl\PzDto\PzDto::translateMessagesUsing()}.
 *
 * @internal
 */
final class Messages
{
    /** @var array<string, array<string, string>> */
    public const LOCALES = [
        'en' => [
            'required' => 'The :field field is required.',
            'not_empty' => 'The :field field must not be empty.',
            'string' => 'The :field field must be a string.',
            'integer' => 'The :field field must be an integer.',
            'numeric' => 'The :field field must be a number.',
            'boolean' => 'The :field field must be true or false.',
            'array' => 'The :field field must be an array.',
            'date' => 'The :field field must be a valid date.',
            'date.after_or_equal' => 'The :field field must be a date after or equal to :min.',
            'date.before_or_equal' => 'The :field field must be a date before or equal to :max.',
            'enum' => 'The selected :field is invalid.',
            'in' => 'The selected :field is invalid.',
            'invalid' => 'The :field field has an invalid value.',
            'email' => 'The :field field must be a valid email address.',
            'url' => 'The :field field must be a valid URL.',
            'uuid' => 'The :field field must be a valid UUID.',
            'regex' => 'The :field field format is invalid.',
            'extension' => 'The :field field must have one of the following extensions: :values.',
            'extension.any' => 'The :field field must have a file extension.',
            'length.min' => 'The :field field must be at least :min characters.',
            'length.max' => 'The :field field must not be greater than :max characters.',
            'min.numeric' => 'The :field field must be at least :min.',
            'min.string' => 'The :field field must be at least :min characters.',
            'min.array' => 'The :field field must be at least :min items.',
            'max.numeric' => 'The :field field must not be greater than :max.',
            'max.string' => 'The :field field must not be greater than :max characters.',
            'max.array' => 'The :field field must not be greater than :max items.',
            'required_if' => 'The :field field is required when :other is :value.',
            'required_with' => 'The :field field is required when :values is present.',
        ],
        'pt_BR' => [
            'required' => 'O campo :field é obrigatório.',
            'not_empty' => 'O campo :field não pode ser vazio.',
            'string' => 'O campo :field deve ser um texto.',
            'integer' => 'O campo :field deve ser um número inteiro.',
            'numeric' => 'O campo :field deve ser um número.',
            'boolean' => 'O campo :field deve ser verdadeiro ou falso.',
            'array' => 'O campo :field deve ser uma lista.',
            'date' => 'O campo :field deve ser uma data válida.',
            'date.after_or_equal' => 'O campo :field deve ser uma data igual ou posterior a :min.',
            'date.before_or_equal' => 'O campo :field deve ser uma data igual ou anterior a :max.',
            'enum' => 'O valor selecionado para :field é inválido.',
            'in' => 'O valor selecionado para :field é inválido.',
            'invalid' => 'O campo :field tem um valor inválido.',
            'email' => 'O campo :field deve ser um e-mail válido.',
            'url' => 'O campo :field deve ser uma URL válida.',
            'uuid' => 'O campo :field deve ser um UUID válido.',
            'regex' => 'O formato do campo :field é inválido.',
            'extension' => 'O campo :field deve ter uma das extensões: :values.',
            'extension.any' => 'O campo :field deve ter uma extensão de arquivo.',
            'length.min' => 'O campo :field deve ter no mínimo :min caracteres.',
            'length.max' => 'O campo :field não pode ter mais que :max caracteres.',
            'min.numeric' => 'O campo :field deve ser no mínimo :min.',
            'min.string' => 'O campo :field deve ter no mínimo :min caracteres.',
            'min.array' => 'O campo :field deve ter no mínimo :min itens.',
            'max.numeric' => 'O campo :field não pode ser maior que :max.',
            'max.string' => 'O campo :field não pode ter mais que :max caracteres.',
            'max.array' => 'O campo :field não pode ter mais que :max itens.',
            'required_if' => 'O campo :field é obrigatório quando :other é :value.',
            'required_with' => 'O campo :field é obrigatório quando :values está presente.',
        ],
    ];

    private static string $locale = 'en';

    /** @var (\Closure(): string)|null Resolve o idioma a cada mensagem (ex.: locale da requisição) */
    private static ?\Closure $localeResolver = null;

    /** @var array<string, string> Textos que sobrescrevem o idioma ativo */
    private static array $overrides = [];

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
     * Idioma fixo (`'pt_BR'`) ou um resolvedor chamado a cada mensagem
     * (`fn () => app()->getLocale()`). Com resolvedor, idioma desconhecido cai
     * no inglês em vez de lançar; `pt-BR`/`pt_br` são aceitos.
     *
     * @param string|callable(): string $locale
     *
     * @throws PzDtoException quando o idioma fixo não existe
     */
    public static function useLocale(string|callable $locale): void
    {
        if (! is_string($locale)) {
            self::$localeResolver = \Closure::fromCallable($locale);

            return;
        }

        $normalized = self::normalizeLocale($locale);
        if ($normalized === null) {
            throw new PzDtoException(sprintf(
                'Unknown PzDto locale "%s". Available: %s.',
                $locale,
                implode(', ', array_keys(self::LOCALES)),
            ));
        }

        self::$localeResolver = null;
        self::$locale = $normalized;
    }

    private static function normalizeLocale(string $locale): ?string
    {
        $candidates = [$locale, str_replace('-', '_', $locale)];
        if (preg_match('/^([a-z]{2})[-_]([a-z]{2})$/i', $locale, $m) === 1) {
            $candidates[] = strtolower($m[1]).'_'.strtoupper($m[2]);
        }
        $candidates[] = strtolower(substr($locale, 0, 2));

        foreach ($candidates as $candidate) {
            if (isset(self::LOCALES[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private static function activeLocale(): string
    {
        if (self::$localeResolver === null) {
            return self::$locale;
        }

        return self::normalizeLocale((string) (self::$localeResolver)()) ?? 'en';
    }

    /**
     * Sobrescreve textos padrão por chave (`['required' => 'Informe :field.']`).
     * Chave desconhecida lança, para pegar erro de digitação cedo. Array vazio limpa.
     *
     * @param array<string, string> $messages
     *
     * @throws PzDtoException
     */
    public static function useMessages(array $messages): void
    {
        foreach (array_keys($messages) as $key) {
            if (! isset(self::LOCALES['en'][$key])) {
                throw new PzDtoException(sprintf('Unknown PzDto message key "%s".', $key));
            }
        }

        self::$overrides = $messages === [] ? [] : $messages + self::$overrides;
    }

    /**
     * @param string $key Chave da mensagem padrão (ver {@see self::LOCALES})
     * @param array<string, scalar> $replace
     */
    public static function make(?string $custom, string $key, string $field, array $replace = []): string
    {
        $replace = ['field' => $field] + $replace;

        if ($custom !== null && self::$translator !== null) {
            return (self::$translator)($custom, $replace);
        }

        $template = $custom ?? self::$overrides[$key] ?? self::LOCALES[self::activeLocale()][$key] ?? self::LOCALES['en'][$key];

        $pairs = [];
        foreach ($replace as $name => $value) {
            $pairs[':'.$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        // Chaves maiores primeiro: ":min" não pode comer o começo de ":minimum".
        uksort($pairs, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return strtr($template, $pairs);
    }
}
