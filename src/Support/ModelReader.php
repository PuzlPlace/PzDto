<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

/**
 * Lê uma Eloquent Model campo a campo do DTO, sem passar pelo `$model->toArray()`.
 *
 * Com `getAttribute()` os casts e accessors da Model são aplicados e as datas
 * chegam como Carbon no fuso da aplicação — o toArray() da Model converteria
 * para string ISO em UTC. Só os campos que o DTO declara são lidos, e relação só
 * entra se já estiver carregada (nunca dispara lazy loading).
 *
 * O pacote não depende do Laravel: a Model é reconhecida pelo nome da classe.
 *
 * @internal
 */
final class ModelReader
{
    private const MODEL = 'Illuminate\\Database\\Eloquent\\Model';

    public static function isModel(mixed $value): bool
    {
        return is_object($value) && is_a($value, self::MODEL);
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(object $model, ClassMetadata $metadata): array
    {
        $values = [];
        foreach ($metadata->properties as $name => $property) {
            [$found, $value] = self::readKey($model, $name, $property);
            if ($found) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /**
     * Atributo (coluna, cast, accessor — em `$appends` ou não) ou relação carregada
     * (`order_items` também encontra a relação `orderItems`).
     *
     * @return array{bool, mixed} [encontrado?, valor]
     */
    public static function readKey(object $model, string $key, ?PropertyMetadata $property = null): array
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        if (array_key_exists($key, $model->getAttributes()) || in_array($key, $model->getAppends(), true) || self::hasAccessor($model, $key)) {
            $value = $model->getAttribute($key);
            if ($value instanceof \DateTimeInterface && $property?->namedType() === 'string') {
                $value = $value->format(self::dateFormat((string) ($model->getCasts()[$key] ?? '')));
            }

            return [true, $value];
        }

        foreach (array_unique([$key, lcfirst(str_replace('_', '', ucwords($key, '_')))]) as $relation) {
            if ($model->relationLoaded($relation)) {
                return [true, $model->getRelation($relation)];
            }
        }

        return [false, null];
    }

    /**
     * `getFooAttribute()` (clássico) ou `foo(): Attribute` (Laravel 9+), mesmo fora de `$appends`.
     */
    private static function hasAccessor(object $model, string $key): bool
    {
        return (method_exists($model, 'hasGetMutator') && $model->hasGetMutator($key))
            || (method_exists($model, 'hasAttributeGetMutator') && $model->hasAttributeGetMutator($key));
    }

    /**
     * Data para prop string: respeita `date:d/m/Y`, `date` → `Y-m-d`, resto → `Y-m-d H:i:s`.
     */
    private static function dateFormat(string $cast): string
    {
        if (str_contains($cast, ':')) {
            return substr($cast, strpos($cast, ':') + 1);
        }

        return in_array($cast, ['date', 'immutable_date'], true) ? 'Y-m-d' : 'Y-m-d H:i:s';
    }
}
