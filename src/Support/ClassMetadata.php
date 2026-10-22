<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\PzDto;

/**
 * Cache de reflexão por classe: propriedades, atributos e o meio de escrevê-las.
 *
 * Percorre a hierarquia inteira (classe filha, BaseDto, …), inclusive propriedades
 * `private` de classes-pai. A escrita usa um closure ligado à classe que declara a
 * propriedade, o que permite inicializar `readonly`, `protected` e `private`.
 *
 * @internal
 */
final class ClassMetadata
{
    /** @var array<class-string, self> */
    private static array $cache = [];

    /** @var array<string, \Closure(object, string, mixed): void> */
    private static array $writers = [];

    /**
     * @param \ReflectionClass<object> $reflection
     * @param array<string, PropertyMetadata> $properties
     * @param array<int, string> $publicNames Públicas na ordem do json_encode (pai antes da filha)
     */
    private function __construct(
        public readonly \ReflectionClass $reflection,
        public readonly array $properties,
        public readonly array $publicNames,
    ) {
        $this->indexes = array_flip(array_keys($properties));
        $this->bitsAsInt = Bits::fitsInInt(count($properties));
        $this->publicIndexes = array_flip($publicNames);
    }

    /** @var array<string, \ReflectionProperty> */
    private array $reflections = [];

    /** @var array<string, int> Posição de cada propriedade (bit em "provided") */
    public readonly array $indexes;

    /** Bits de "provided" cabem num int (até 63 propriedades)? */
    public readonly bool $bitsAsInt;

    /** @var array<string, int> Posição de cada pública (índice no snapshot original) */
    public readonly array $publicIndexes;

    /**
     * @throws PzDtoException quando o nome não é uma propriedade pública
     */
    public function assertPublic(string $name): void
    {
        if (! isset($this->publicIndexes[$name])) {
            throw new PzDtoException(sprintf('%s has no public property "%s".', $this->reflection->getName(), $name));
        }
    }

    /**
     * @param class-string $class
     */
    public static function for(string $class): self
    {
        return self::$cache[$class] ??= self::build($class);
    }

    public function write(object $instance, PropertyMetadata $property, mixed $value): void
    {
        $writer = self::$writers[$property->declaringClass] ??= \Closure::bind(
            static function (object $instance, string $name, mixed $value): void {
                $instance->$name = $value;
            },
            null,
            $property->declaringClass,
        );

        $writer($instance, $property->name, $value);
    }

    /**
     * @return array{bool, mixed} [inicializada?, valor]
     */
    public function read(object $instance, PropertyMetadata $property): array
    {
        $reflection = $this->reflections[$property->name] ??= new \ReflectionProperty($property->declaringClass, $property->name);
        if (! $reflection->isInitialized($instance)) {
            return [false, null];
        }

        return [true, $reflection->getValue($instance)];
    }

    /**
     * @param class-string $class
     */
    private static function build(string $class): self
    {
        $reflection = new \ReflectionClass($class);
        $properties = [];

        // O estado interno do próprio PzDto (rastreamento) não é campo de DTO.
        for ($current = $reflection; $current !== false && $current->getName() !== PzDto::class; $current = $current->getParentClass()) {
            foreach ($current->getProperties() as $property) {
                // Filha vence: propriedade redeclarada já foi lida na volta anterior.
                if ($property->isStatic() || isset($properties[$property->getName()])) {
                    continue;
                }
                if ($property->getDeclaringClass()->getName() !== $current->getName()) {
                    continue;
                }
                $properties[$property->getName()] = self::describe($property);
            }
        }

        // Ordem da tabela de propriedades do PHP: da raiz para a filha, cada nome
        // na posição em que apareceu primeiro.
        $publicNames = [];
        $chain = [];
        for ($current = $reflection; $current !== false && $current->getName() !== PzDto::class; $current = $current->getParentClass()) {
            array_unshift($chain, $current);
        }
        foreach ($chain as $current) {
            foreach ($current->getProperties() as $property) {
                $name = $property->getName();
                if (isset($properties[$name]) && $properties[$name]->isPublic && ! in_array($name, $publicNames, true)) {
                    $publicNames[] = $name;
                }
            }
        }

        return new self($reflection, $properties, $publicNames);
    }

    private static function describe(\ReflectionProperty $property): PropertyMetadata
    {
        $dtoClass = null;
        $enumClass = null;

        foreach ($property->getAttributes() as $attribute) {
            // Casa pelo nome curto para aceitar também App\Helpers\DtoAttribute e
            // App\Helpers\EnumAttribute da FromArrayTrait durante a migração.
            $shortName = substr((string) strrchr('\\'.$attribute->getName(), '\\'), 1);
            if ($shortName === 'DtoAttribute') {
                $dtoClass = self::conversionTarget($property, $attribute, 'DtoAttribute');
            } elseif ($shortName === 'EnumAttribute') {
                $enumClass = self::conversionTarget($property, $attribute, 'EnumAttribute');
            }
        }

        if ($enumClass !== null && ! enum_exists($enumClass)) {
            throw new PzDtoException(sprintf(
                '#[EnumAttribute] of %s::$%s points to "%s", which is not an enum.',
                $property->getDeclaringClass()->getName(),
                $property->getName(),
                $enumClass,
            ));
        }
        if ($dtoClass !== null && ! method_exists($dtoClass, 'from')) {
            throw new PzDtoException(sprintf(
                '#[DtoAttribute] of %s::$%s points to "%s", which has no static from().',
                $property->getDeclaringClass()->getName(),
                $property->getName(),
                $dtoClass,
            ));
        }

        $rules = array_map(
            static fn (\ReflectionAttribute $attribute): ValidationRule => $attribute->newInstance(),
            $property->getAttributes(ValidationRule::class, \ReflectionAttribute::IS_INSTANCEOF),
        );

        return new PropertyMetadata(
            name: $property->getName(),
            declaringClass: $property->getDeclaringClass()->getName(),
            type: $property->getType(),
            isPublic: $property->isPublic(),
            hasDefault: $property->hasDefaultValue(),
            default: $property->hasDefaultValue() ? $property->getDefaultValue() : null,
            dtoClass: $dtoClass,
            enumClass: $enumClass,
            rules: $rules,
        );
    }

    /**
     * Lê o destino de #[DtoAttribute]/#[EnumAttribute] sem instanciar o atributo
     * (a classe legada pode não existir no projeto).
     */
    private static function conversionTarget(\ReflectionProperty $property, \ReflectionAttribute $attribute, string $label): string
    {
        $arguments = $attribute->getArguments();
        $target = (string) ($arguments['namespace'] ?? $arguments[0] ?? '');

        if ($target !== '') {
            return ltrim($target, '\\');
        }

        $type = $property->getType();
        if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
            return $type->getName();
        }

        throw new PzDtoException(sprintf(
            '#[%s] of %s::$%s needs the target class (e.g. #[%s(Foo::class)]) because the property type is not a class.',
            $label,
            $property->getDeclaringClass()->getName(),
            $property->getName(),
            $label,
        ));
    }
}
