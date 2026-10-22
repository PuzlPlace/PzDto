<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;

/**
 * Converte o valor recebido para o tipo declarado da propriedade.
 *
 * Regras de ausência/nulo:
 * - chave ausente → default da propriedade; sem default, null (nullable) ou o
 *   "zero" do tipo (`0`, `''`, `false`, `[]`, DTO vazio);
 * - null explícito → null (nullable); senão default; senão o "zero" do tipo;
 * - enum e data não têm "zero": sem valor em propriedade não-nullable é erro.
 *
 * @internal
 */
final class Caster
{
    private const BUILTIN_ZERO = [
        'int' => 0,
        'float' => 0.0,
        'string' => '',
        'bool' => false,
        'array' => [],
    ];

    /**
     * @throws CastError
     */
    public static function cast(PropertyMetadata $property, mixed $value, bool $present): mixed
    {
        if (! $present) {
            return $property->hasDefault ? $property->default : self::missing($property);
        }

        if ($value === null || ($value === '' && self::emptyStringIsNull($property))) {
            if ($property->allowsNull()) {
                return null;
            }

            return $property->hasDefault ? $property->default : self::missing($property);
        }

        if ($property->dtoClass !== null) {
            return $property->isArray()
                ? self::castList($value, $property, fn (mixed $item): mixed => self::toDto($property->dtoClass, $item))
                : self::toDto($property->dtoClass, $value);
        }

        if ($property->enumClass !== null) {
            return $property->isArray()
                ? self::castList($value, $property, fn (mixed $item): mixed => self::toEnum($property->enumClass, $item, $property->name))
                : self::toEnum($property->enumClass, $value, $property->name);
        }

        $type = $property->type;

        return match (true) {
            $type instanceof \ReflectionNamedType => self::castNamed($type->getName(), $value, $property->name),
            $type instanceof \ReflectionUnionType => self::castUnion($type, $value, $property->name),
            default => $value,
        };
    }

    /**
     * Valor para propriedade sem dado utilizável (ausente ou null em não-nullable).
     *
     * @throws CastError
     */
    private static function missing(PropertyMetadata $property): mixed
    {
        if ($property->allowsNull()) {
            return null;
        }

        if ($property->dtoClass !== null || $property->enumClass !== null) {
            if ($property->isArray()) {
                return [];
            }
            if ($property->dtoClass !== null) {
                return self::blankDto($property->dtoClass);
            }

            throw CastError::message(Messages::make(null, 'The :field field is required.', $property->name));
        }

        $type = $property->type;
        $typeName = match (true) {
            $type instanceof \ReflectionNamedType => $type->getName(),
            $type instanceof \ReflectionUnionType => $type->getTypes()[0]->getName(),
            default => 'mixed',
        };

        if (array_key_exists($typeName, self::BUILTIN_ZERO)) {
            return self::BUILTIN_ZERO[$typeName];
        }
        if (in_array($typeName, ['mixed', 'iterable', 'object', 'null'], true)) {
            return null;
        }
        if (class_exists($typeName) && ! enum_exists($typeName) && ! is_a($typeName, \DateTimeInterface::class, true)
            && method_exists($typeName, 'from')) {
            return self::blankDto($typeName);
        }

        throw CastError::message(Messages::make(null, 'The :field field is required.', $property->name));
    }

    /**
     * `''` de formulário/query string conta como "sem valor" em tipos onde string
     * vazia não tem significado (número, data, enum, DTO).
     */
    private static function emptyStringIsNull(PropertyMetadata $property): bool
    {
        if ($property->dtoClass !== null || $property->enumClass !== null) {
            return true;
        }

        $typeName = $property->namedType();
        if ($typeName === null) {
            return false;
        }

        if (in_array($typeName, ['int', 'float'], true)) {
            return true;
        }
        if (in_array($typeName, ['string', 'bool', 'array', 'mixed', 'iterable', 'object', 'callable'], true)) {
            return false;
        }

        return class_exists($typeName) || interface_exists($typeName);
    }

    /**
     * @throws CastError
     */
    private static function castNamed(string $typeName, mixed $value, string $field): mixed
    {
        return match ($typeName) {
            'int' => self::toInt($value, $field),
            'float' => self::toFloat($value, $field),
            'string' => self::toString($value, $field),
            'bool' => self::toBool($value),
            'array' => self::toArray($value),
            'mixed', 'iterable', 'object', 'callable', 'null', 'false', 'true' => $value,
            default => self::castClass($typeName, $value, $field),
        };
    }

    /**
     * @throws CastError
     */
    private static function castUnion(\ReflectionUnionType $type, mixed $value, string $field): mixed
    {
        $members = [];
        foreach ($type->getTypes() as $member) {
            if ($member instanceof \ReflectionNamedType && $member->getName() !== 'null') {
                $members[] = $member;
            }
        }

        // 1º: o valor já é de um dos tipos aceitos → mantém como veio.
        foreach ($members as $member) {
            $name = $member->getName();
            if ($member->isBuiltin() ? self::matchesBuiltin($name, $value) : $value instanceof $name) {
                return $value;
            }
        }

        // 2º: array/objeto → tenta antes as classes (DTO/enum) da union.
        if (is_array($value) || is_object($value)) {
            usort($members, fn (\ReflectionNamedType $a, \ReflectionNamedType $b): int => $a->isBuiltin() <=> $b->isBuiltin());
        }

        $firstError = null;
        foreach ($members as $member) {
            try {
                return self::castNamed($member->getName(), $value, $field);
            } catch (CastError $error) {
                $firstError ??= $error;
            }
        }

        throw $firstError ?? CastError::message(Messages::make(null, 'The :field field has an invalid value.', $field));
    }

    private static function matchesBuiltin(string $typeName, mixed $value): bool
    {
        return match ($typeName) {
            'int' => is_int($value),
            'float' => is_float($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'false' => $value === false,
            'true' => $value === true,
            'array' => is_array($value),
            'iterable' => is_iterable($value),
            'object' => is_object($value),
            'callable' => is_callable($value),
            'mixed' => true,
            default => false,
        };
    }

    /**
     * @throws CastError
     */
    private static function castClass(string $typeName, mixed $value, string $field): mixed
    {
        if ($value instanceof $typeName) {
            return $value;
        }
        if (enum_exists($typeName)) {
            return self::toEnum($typeName, $value, $field);
        }
        if (is_a($typeName, \DateTimeInterface::class, true)) {
            return self::toDate($typeName, $value, $field);
        }
        if (class_exists($typeName) && method_exists($typeName, 'from')) {
            return self::toDto($typeName, $value);
        }

        return $value;
    }

    /**
     * @throws CastError
     */
    private static function toInt(mixed $value, string $field): int
    {
        $value = self::unwrapEnum($value);

        return match (true) {
            is_int($value) => $value,
            is_bool($value), is_float($value) => (int) $value,
            is_string($value) && is_numeric(trim($value)) => (int) (float) trim($value),
            default => throw CastError::message(Messages::make(null, 'The :field field must be an integer.', $field)),
        };
    }

    /**
     * @throws CastError
     */
    private static function toFloat(mixed $value, string $field): float
    {
        $value = self::unwrapEnum($value);
        if (is_string($value)) {
            $value = trim($value);
            // Vírgula decimal pt-BR sem separador de milhar: "1,5" → 1.5
            if (preg_match('/^[+-]?\d+,\d+$/', $value) === 1) {
                $value = str_replace(',', '.', $value);
            }
        }

        return match (true) {
            is_float($value) => $value,
            is_int($value), is_bool($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => throw CastError::message(Messages::make(null, 'The :field field must be a number.', $field)),
        };
    }

    /**
     * @throws CastError
     */
    private static function toString(mixed $value, string $field): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => (string) $value,
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \Stringable => (string) $value,
            default => throw CastError::message(Messages::make(null, 'The :field field must be a string.', $field)),
        };
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
        }

        return $value === 1 || $value === 1.0;
    }

    /**
     * @return array<mixed>
     */
    private static function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return method_exists($value, 'toArray') ? (array) $value->toArray() : get_object_vars($value);
        }

        return (array) $value;
    }

    /**
     * @param class-string $class
     *
     * @throws CastError
     */
    private static function toDto(string $class, mixed $value): mixed
    {
        if ($value instanceof $class) {
            return $value;
        }

        try {
            return $class::from($value ?? []);
        } catch (PzDtoValidationException $exception) {
            throw new CastError($exception->errors());
        }
    }

    /**
     * DTO aninhado que não veio: PzDto vazio sem validar; DTO legado via from([]).
     *
     * @param class-string $class
     */
    private static function blankDto(string $class): mixed
    {
        return is_subclass_of($class, PzDto::class) ? $class::blank() : $class::from([]);
    }

    /**
     * @param class-string<\UnitEnum> $class
     *
     * @throws CastError
     */
    private static function toEnum(string $class, mixed $value, string $field): \UnitEnum
    {
        if ($value instanceof $class) {
            return $value;
        }

        $value = self::unwrapEnum($value);
        if (is_string($value)) {
            $value = trim($value);
        }

        if (is_scalar($value)) {
            foreach ($class::cases() as $case) {
                if ($case instanceof \BackedEnum && self::sameScalar($case->value, $value)) {
                    return $case;
                }
            }
            foreach ($class::cases() as $case) {
                if ($case->name === $value) {
                    return $case;
                }
            }
        }

        throw CastError::message(Messages::make(null, 'The selected :field is invalid.', $field));
    }

    private static function sameScalar(int|string $caseValue, int|float|string|bool $value): bool
    {
        if (is_bool($value)) {
            return false;
        }
        if (is_int($caseValue)) {
            return is_numeric($value) && (float) $value === (float) $caseValue;
        }

        return (string) $value === $caseValue;
    }

    /**
     * @param class-string<\DateTimeInterface> $class
     *
     * @throws CastError
     */
    private static function toDate(string $class, mixed $value, string $field): \DateTimeInterface
    {
        $target = interface_exists($class) || (new \ReflectionClass($class))->isAbstract() ? \DateTime::class : $class;

        try {
            if ($value instanceof \DateTimeInterface) {
                return $target::createFromInterface($value);
            }
            if (is_int($value)) {
                return new $target('@'.$value);
            }
            if (is_string($value)) {
                return new $target(trim($value));
            }
            // DateTime serializado por json_encode: {date, timezone_type, timezone}
            if (is_array($value) && isset($value['date']) && is_string($value['date'])) {
                $timezone = isset($value['timezone']) && is_string($value['timezone'])
                    ? new \DateTimeZone($value['timezone'])
                    : null;

                return new $target($value['date'], $timezone);
            }
        } catch (\Exception) {
            // Cai no erro de validação abaixo.
        }

        throw CastError::message(Messages::make(null, 'The :field field must be a valid date.', $field));
    }

    /**
     * @param callable(mixed): mixed $castItem
     *
     * @return array<int, mixed>
     *
     * @throws CastError
     */
    private static function castList(mixed $value, PropertyMetadata $property, callable $castItem): array
    {
        if (is_object($value) && ! $value instanceof \UnitEnum) {
            $value = self::toArray($value);
        }
        if (! is_array($value)) {
            throw CastError::message(Messages::make(null, 'The :field field must be an array.', $property->name));
        }

        $result = [];
        $errors = [];
        foreach ($value as $key => $item) {
            if ($item === null) {
                $result[] = null;
                continue;
            }
            try {
                $result[] = $castItem($item);
            } catch (CastError $error) {
                foreach ($error->errors as $path => $messages) {
                    $errors[self::join((string) $key, (string) $path)] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw new CastError($errors);
        }

        return $result;
    }

    private static function unwrapEnum(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    public static function join(string $prefix, string $path): string
    {
        return $path === '' ? $prefix : ($prefix === '' ? $path : $prefix.'.'.$path);
    }
}
