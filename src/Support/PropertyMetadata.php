<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

use Puzl\PzDto\Contracts\ValidationRule;

/**
 * Tudo que o PzDto precisa saber de uma propriedade, lido uma vez por classe.
 *
 * @internal
 */
final class PropertyMetadata
{
    /**
     * @param class-string|null    $dtoClass  Destino de #[DtoAttribute]
     * @param class-string|null    $enumClass Destino de #[EnumAttribute]
     * @param array<ValidationRule> $rules
     */
    public function __construct(
        public readonly string $name,
        public readonly string $declaringClass,
        public readonly ?\ReflectionType $type,
        public readonly bool $hasDefault,
        public readonly mixed $default,
        public readonly ?string $dtoClass,
        public readonly ?string $enumClass,
        public readonly array $rules,
    ) {
    }

    public function allowsNull(): bool
    {
        return $this->type === null || $this->type->allowsNull();
    }

    public function isArray(): bool
    {
        return $this->type instanceof \ReflectionNamedType && $this->type->getName() === 'array';
    }

    /**
     * Nome do tipo quando é um tipo simples (`int`, `?int`, `FooDto`); null em union/intersection/sem tipo.
     */
    public function namedType(): ?string
    {
        return $this->type instanceof \ReflectionNamedType ? $this->type->getName() : null;
    }
}
