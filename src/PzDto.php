<?php

declare(strict_types=1);

namespace Puzl\PzDto;

use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\Support\Caster;
use Puzl\PzDto\Support\CastError;
use Puzl\PzDto\Support\ClassMetadata;
use Puzl\PzDto\Support\Messages;

/**
 * Base dos DTOs: basta estender (direto ou por uma base do projeto).
 *
 * ```php
 * abstract class BaseDto extends PzDto {}
 *
 * class FinanceCreateDto extends BaseDto
 * {
 *     #[Required, Length(max: 120)]
 *     public string $description;
 *
 *     #[DtoAttribute(FinanceItemDto::class)]
 *     public array $items;
 * }
 *
 * $dto = FinanceCreateDto::from($request->validated());
 * ```
 */
abstract class PzDto
{
    /**
     * Carregar dados através de array | Model | stdClass | DTO | string JSON.
     * Estrutura dos dados precisa ser compatível com a classe.
     *
     * @param array<string, mixed>|object|string|null $values
     * @param array<int, string>|null $destructuring Chaves cujo conteúdo (array) é mesclado na raiz
     *
     * @throws PzDtoValidationException quando algum campo não converte ou não passa nas regras
     * @throws PzDtoException quando a entrada não pode ser lida
     */
    public static function from(mixed $values, ?array $destructuring = null): static
    {
        $values = self::normalizeInput($values);

        foreach ($destructuring ?? [] as $key) {
            $nested = $values[$key] ?? null;
            if (is_object($nested)) {
                $nested = self::normalizeInput($nested);
            }
            if (is_array($nested)) {
                $values = array_merge($values, $nested);
            }
        }

        return static::create($values);
    }

    /**
     * Instanciar a própria classe, convertendo e validando cada propriedade.
     *
     * @param array<string, mixed>|null $values
     *
     * @throws PzDtoValidationException
     */
    public static function create(?array $values): static
    {
        return static::build($values ?? [], true);
    }

    /**
     * Instância "vazia", sem regras de validação: o que um DTO aninhado
     * não-nullable recebe quando a chave não veio (mesmo papel do `from([])` legado).
     * Propriedades sem "zero" possível (enum, data) ficam não inicializadas.
     */
    public static function blank(): static
    {
        return static::build([], false);
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function build(array $values, bool $validate): static
    {
        $metadata = ClassMetadata::for(static::class);

        if ($metadata->reflection->isAbstract()) {
            throw new PzDtoException(sprintf('Cannot create abstract DTO %s.', static::class));
        }

        $instance = $metadata->reflection->newInstanceWithoutConstructor();
        $errors = [];

        foreach ($metadata->properties as $name => $property) {
            $present = array_key_exists($name, $values);
            $raw = $present ? $values[$name] : null;

            try {
                $value = Caster::cast($property, $raw, $present);
            } catch (CastError $error) {
                foreach ($error->errors as $path => $messages) {
                    $errors[Caster::join($name, (string) $path)] = $messages;
                }
                continue;
            }

            foreach ($validate ? $property->rules : [] as $rule) {
                $message = $rule->validate($value, $raw, $name);
                if ($message !== null) {
                    $errors[$name][] = $message;
                }
            }

            try {
                $metadata->write($instance, $property, $value);
            } catch (\TypeError $error) {
                throw new PzDtoException(
                    sprintf('Cannot assign %s::$%s: %s', static::class, $name, $error->getMessage()),
                    previous: $error,
                );
            }
        }

        if ($validate && $errors !== []) {
            throw new PzDtoValidationException($errors, static::class);
        }

        return $instance;
    }

    /**
     * Revalida o estado atual do objeto (útil depois de alterar propriedades à mão).
     *
     * @throws PzDtoValidationException
     */
    public function assertValid(): void
    {
        $metadata = ClassMetadata::for(static::class);
        $errors = [];

        foreach ($metadata->properties as $name => $property) {
            if ($property->rules === []) {
                continue;
            }

            [$initialized, $value] = $metadata->read($this, $property);
            if (! $initialized) {
                continue;
            }

            foreach ($property->rules as $rule) {
                $message = $rule->validate($value, $value, $name);
                if ($message !== null) {
                    $errors[$name][] = $message;
                }
            }
        }

        if ($errors !== []) {
            throw new PzDtoValidationException($errors, static::class);
        }
    }

    /**
     * Propriedades públicas como array (enums viram o value; DTOs aninhados, arrays).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return json_decode($this->toJson(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /**
     * Registra um tradutor para as mensagens customizadas dos atributos de validação.
     *
     * ```php
     * PzDto::translateMessagesUsing(fn (string $key, array $replace) => trans($key, $replace));
     * ```
     *
     * @param (callable(string, array<string, scalar>): string)|null $translator
     */
    public static function translateMessagesUsing(?callable $translator): void
    {
        Messages::translateUsing($translator);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeInput(mixed $values): array
    {
        if ($values === null) {
            return [];
        }

        if (is_string($values)) {
            try {
                $decoded = json_decode($values, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new PzDtoException(
                    sprintf('Invalid JSON given to %s::from(): %s', static::class, $exception->getMessage()),
                    previous: $exception,
                );
            }
            if (! is_array($decoded)) {
                throw new PzDtoException(sprintf('JSON given to %s::from() must be an object or array.', static::class));
            }

            return $decoded;
        }

        if (is_array($values)) {
            return $values;
        }

        if ($values instanceof self) {
            // Só as públicas (como no toArray), preservando objetos (DateTime, enum, DTO).
            $public = [];
            foreach ((new \ReflectionObject($values))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if (! $property->isStatic() && $property->isInitialized($values)) {
                    $public[$property->getName()] = $property->getValue($values);
                }
            }

            return $public;
        }

        if (is_object($values)) {
            return method_exists($values, 'toArray') ? (array) $values->toArray() : get_object_vars($values);
        }

        throw new PzDtoException(sprintf(
            '%s::from() expects array, object or JSON string, %s given.',
            static::class,
            get_debug_type($values),
        ));
    }
}
