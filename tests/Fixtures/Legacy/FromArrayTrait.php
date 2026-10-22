<?php

namespace Puzl\PzDto\Tests\Fixtures\Legacy;

// Cópia fiel de puzl/api app/Helpers/FromArrayTrait.php (só o namespace muda),
// usada para provar que PzDto e a trait convivem no mesmo projeto.
// Carregada com require_once pelos testes (contém várias classes + helper collect()).

if (! function_exists(__NAMESPACE__.'\\collect')) {
    function collect(array $items): object
    {
        return new class($items) {
            public function __construct(private array $items)
            {
            }

            public function first(): mixed
            {
                return $this->items === [] ? null : reset($this->items);
            }
        };
    }
}

trait FromArrayTrait
{
    /**
     * Carregar dados através de objetos array|model|BaseDto.
     * Estrutura dos dados precisa ser compatível com a classe.
     *
     * @param mixed $values array|model|stdclass|BaseDto
     * @param array|null $destructuring Chaves para desestruturar e mesclar os valores dentro do array fornecido
     *
     * @return self
     */
    public static function from(mixed $values, ?array $destructuring = null): self
    {
        if (is_string($values)) {
            $values = json_decode($values, true);
        }
        if ($destructuring && is_array($values)) {
            foreach ($destructuring as $key) {
                $values = array_merge($values, $values[$key]);
            }
        }
        $content = [];
        if (is_array($values)) {
            $content = $values;
        }
        if (is_object($values)) {
            if (method_exists($values, 'toArray')) {
                $content = $values->toArray();
            } else {
                $content = (array) $values;
            }
        }
        return self::create($content);
    }

    public function toArray(): array
    {
        return json_decode(json_encode($this, JSON_PRESERVE_ZERO_FRACTION), true);
    }
    public function toJson(): string
    {
        return json_encode($this, JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Instanciar a própria classe e tratar atributos de classe
     *
     * @param array|null $values
     *
     * @return self
     */
    public static function create(array|null $values): self
    {
        $instance = new self();
        $class = new \ReflectionClass(static::class);
        foreach ($class->getProperties() as $prop) {
            $field = $prop->getName();
            self::getDecorators($prop, $values[$field]?? null);
            $instance->$field = self::getFieldValue($prop, $values[$field] ?? null);
        }
        return $instance;
    }


    private static function getDecorators(\ReflectionProperty $prop, mixed $value): void
    {
        $annotations = $prop->getAttributes();
        foreach ($annotations as $annotation) {
            match($annotation->getName()) {
                IsString::class => self::handleStringAttValue($prop, $annotation, $value),
                IsInt::class => self::handleIntAttValue($prop, $annotation, $value),
                IsFloat::class => self::handleFloatAttValue($prop, $annotation, $value),
                IsBoolean::class => self::handleBoolAttValue($prop, $annotation, $value),
                IsNotEmpty::class => self::handleNotEmptyAttValue($prop, $annotation, $value),
                default => '',
            };
        }
    }
    private static function getFieldValue(\ReflectionProperty $prop, mixed $value): mixed
    {
        $annotations = $prop->getAttributes();
        return match (count($annotations) > 0) {
            true => self::handleAnnotations($prop, $value, $annotations[0]),
            false => self::handleBasicTypes($prop, $value),
        };
    }

    /**
     * @param \ReflectionProperty $prop
     * @param mixed $value
     * @param mixed $annotation
     * @return mixed
     * @throws \Exception
     */
    private static function handleAnnotations(\ReflectionProperty $prop, mixed $value, mixed $annotation): mixed
    {
        return match ($annotation->getName()) {
            EnumAttribute::class => self::handleEnumAttValue($prop, $annotation, $value),
            DtoAttribute::class => self::handleDtoAttValue($prop, $annotation, $value),
            default => $value,
        };
    }

    /**
     * @param \ReflectionProperty $prop
     * @param \ReflectionAttribute $att
     * @param mixed $value
     * @return mixed
     * @throws \Exception
     */
    private static function handleStringAttValue(\ReflectionProperty $prop, \ReflectionAttribute $att, mixed $value): void
    {
        if (! is_string($value)) {
            throw new \Exception($prop->name. ' value must be a string ' . $prop->class);
        }
    }

    /**
     * @param \ReflectionProperty $prop
     * @param \ReflectionAttribute $att
     * @param mixed $value
     * @return mixed
     * @throws \Exception
     */
    private static function handleIntAttValue(\ReflectionProperty $prop, \ReflectionAttribute $att, mixed $value):  void
    {
        if (! is_int($value)) {
            throw new \Exception($prop->name. ' value must be an integer ' . $prop->class);
        }
    }

    /**
     * @param \ReflectionProperty $prop
     * @param \ReflectionAttribute $att
     * @param mixed $value
     * @return mixed
     * @throws \Exception
     */
    private static function handleFloatAttValue(\ReflectionProperty $prop, \ReflectionAttribute $att, mixed $value): void
    {
        if (! is_int($value)) {
            throw new \Exception($prop->name. ' value must be an float ' . $prop->class);
        }
    }

    /**
     * @param \ReflectionProperty $prop
     * @param \ReflectionAttribute $att
     * @param mixed $value
     * @return mixed
     * @throws \Exception
     */
    private static function handleBoolAttValue(\ReflectionProperty $prop, \ReflectionAttribute $att, mixed $value):  void
    {
        if (! is_int($value)) {
            throw new \Exception($prop->name. ' value must be an boolean ' . $prop->class);
        }
    }

    /**
     * @param \ReflectionProperty $prop
     * @param \ReflectionAttribute $att
     * @param mixed $value
     * @return mixed
     * @throws \Exception
     */
    private static function handleNotEmptyAttValue(\ReflectionProperty $prop, \ReflectionAttribute $att, mixed $value):  void
    {
        if (empty($value)) {
            throw new \Exception($prop->name . ' value must be non-empty in ' . $prop->class);
        }
    }
    private static function handleBasicTypes(\ReflectionProperty $prop, mixed $value): mixed
    {
        $propTypeName = self::getPropTypeName($prop, $value);
        return match ($propTypeName) {
            'int', 'integer' => self::handleIntValue($prop, $value),
            'string' => self::handleStringValue($prop, $value),
            'float' => self::handleFloatValue($prop, $value),
            'bool' => self::handleBoolValue($prop, $value),
            'DateTime' => self::handleDateTimeValue($prop, $value),
            'array' => self::handleArrayValue($prop, $value),
            'mixed' => self::handleMixedValue($prop, $value),
            default => self::handleOtherTypes($prop, $value),
        };
    }

    private static function getPropTypeName(\ReflectionProperty $prop, mixed $value): string
    {
        return match ($prop->getType() instanceof \ReflectionUnionType) {
            true => match (is_null($value)) {
                true => $prop->getType()->getTypes()[0]->getName(),
                false => gettype($value),
            },
            false => $prop->getType()->getName(),
        };
    }

    private static function handleEnumAttValue(\ReflectionProperty $prop, \ReflectionAttribute $att, mixed $value): \UnitEnum|array|null
    {
        $attInstance = $att->newInstance();
        $namespace = $attInstance->namespace
            ? $attInstance->namespace
            : $prop->getType()->getName();
        $class = new \ReflectionClass($namespace);
        $execute = function ($class, $prop, $item) {
            if (is_null($item)) {
                if ($prop->getDefaultValue()) {
                    return $prop->getDefaultValue();
                }
                if ($prop->getType()->allowsNull()) {
                    return null;
                }
            }
            $consts = $class->getConstants();
            $const = array_filter($consts, fn ($const) => $item == $const->value);
            if (!$const && $item instanceof \UnitEnum) {
                $const = array_filter($consts, fn ($const) => $item->value == $const->value);
            }
            return $const
                ? reset($const)
                : collect($consts)->first();
        };
        if ($prop->getType()->getName() === 'array') {
            $result = [];
            foreach ($value ?? [] as $current) {
                $result[] = $execute($class, $prop, $current);
            }
            return $result;
        }
        return $execute($class, $prop, $value);
    }

    private static function handleDtoAttValue(\ReflectionProperty $prop, \ReflectionAttribute $att, mixed $value)
    {
        $attInstance = $att->newInstance();
        $namespace = $attInstance->namespace
            ? $attInstance->namespace
            : $prop->getType()->getName();
        $execute = function ($namespace, $prop, $item) {
            if (is_null($item)) {
                if ($prop->getDefaultValue()) {
                    return $prop->getDefaultValue();
                }
                if ($prop->getType()->allowsNull()) {
                    return null;
                }
            }
            return $namespace::from($item ?? []);
        };
        if ($prop->getType()->getName() === 'array') {
            $result = [];
            foreach ($value ?? [] as $current) {
                $result[] = $execute($namespace, $prop, $current);
            }
            return $result;
        }
        return $execute($namespace, $prop, $value);
    }

    private static function handleIntValue(\ReflectionProperty $prop, mixed $value): int|null
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if ($value) {
            return (int) $value;
        }
        if ($defaultValue) {
            return (int) $defaultValue;
        }
        return $allowsNull ? null : 0;
    }

    private static function handleStringValue(\ReflectionProperty $prop, mixed $value): string|null
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if (!is_null($value)) {
            return (string) $value;
        }
        if ($defaultValue) {
            return (string) $defaultValue;
        }
        return $allowsNull ? null : '';
    }

    private static function handleFloatValue(\ReflectionProperty $prop, mixed $value): float|null
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if ($value) {
            return (float) $value;
        }
        if ($defaultValue) {
            return (float) $defaultValue;
        }
        return $allowsNull ? null : 0;
    }

    private static function handleBoolValue(\ReflectionProperty $prop, mixed $value): bool|null
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if (!is_null($value)) {
            return ($value === true) || ($value === 'true') || ($value === 1) || ($value === '1');
        }
        if ($defaultValue) {
            return (bool) $defaultValue;
        }
        return $allowsNull ? null : false;
    }

    private static function handleDateTimeValue(\ReflectionProperty $prop, mixed $value): \DateTime|null
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if ($value) {
            return new \DateTime($value);
        }
        if ($defaultValue) {
            return $defaultValue;
        }
        return $allowsNull ? null : new \DateTime('0000-00-00 00:00:00');
    }

    private static function handleArrayValue(\ReflectionProperty $prop, mixed $value): array|null
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if ($value) {
            return (array) $value;
        }
        if ($defaultValue) {
            return (array) $defaultValue;
        }
        return $allowsNull ? null : [];
    }

    private static function handleMixedValue(\ReflectionProperty $prop, mixed $value): mixed
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if (is_null($value)) {
            if ($defaultValue) {
                return $defaultValue;
            }
            if ($allowsNull) {
                return null;
            }
        }
        return $value;
    }

    private static function handleOtherTypes(\ReflectionProperty $prop, mixed $value): mixed
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if (is_null($value)) {
            if ($defaultValue) {
                return $defaultValue;
            }
            if ($allowsNull) {
                return null;
            }
        }
        // Instanciar Enumerator/DTO
        $propTypeName = $prop->getType()->getName();
        if (class_exists($propTypeName)) {
            $class = new \ReflectionClass($propTypeName);
            if ($class->isEnum()) {
                $consts = $class->getConstants();
                $const = array_filter($consts, fn ($const) => $value == $const->value);
                if (!$const && $value instanceof \UnitEnum) {
                    $const = array_filter($consts, fn ($const) => $value->value == $const->value);
                }
                return $const
                    ? reset($const)
                    : collect($consts)->first();
            }
            // Se não for enum/dto , retorna o valor
            if (! method_exists($propTypeName, 'from')) {
                return $value;
            }
            return $prop->getType()->getName()::from($value ?? []);
        }
        return null;
    }
}

#[\Attribute]
class EnumAttribute extends SimpleObject
{
}

#[\Attribute]
class DtoAttribute extends SimpleObject
{
}
#[\Attribute]
class IsString extends SimpleObject
{
    public function __construct(
    ) {
    }
}

#[\Attribute]
class IsInt extends SimpleObject
{
    public function __construct(
    ) {
    }
}

#[\Attribute]
class IsFloat extends SimpleObject
{
    public function __construct(
    ) {
    }
}

#[\Attribute]
class IsBoolean extends SimpleObject
{
    public function __construct(
    ) {
    }
}

#[\Attribute]
class IsNotEmpty extends SimpleObject
{
    public function __construct(
    ) {
    }
}

class SimpleObject
{
    public function __construct(
        public string $namespace = '',
    ) {
    }
}