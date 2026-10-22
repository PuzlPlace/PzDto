<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

use Puzl\PzDto\Contracts\ContextualValidationRule;
use Puzl\PzDto\Contracts\ValidationRule;

/**
 * Tudo que o PzDto precisa saber de uma propriedade, lido uma vez por classe.
 *
 * @internal
 */
final class PropertyMetadata
{
    /**
     * @param class-string|null    $dtoClass  Destino de #[PzDtoAttribute]
     * @param class-string|null    $enumClass Destino de #[PzEnumAttribute]
     * @param array<ValidationRule|ContextualValidationRule> $rules
     */
    public function __construct(
        public readonly string $name,
        public readonly string $declaringClass,
        public readonly ?\ReflectionType $type,
        public readonly bool $isPublic,
        public readonly bool $hasDefault,
        public readonly mixed $default,
        public readonly ?string $dtoClass,
        public readonly ?string $enumClass,
        public readonly array $rules,
    ) {
    }

    /**
     * Roda as regras da propriedade e devolve as mensagens de erro.
     *
     * @param array<string, mixed> $input Valores brutos do nível (para regras contextuais)
     *
     * @return array<int, string>
     */
    public function validate(mixed $value, mixed $raw, array $input): array
    {
        $messages = [];
        foreach ($this->rules as $rule) {
            $message = $rule instanceof ContextualValidationRule
                ? $rule->validate($value, $raw, $this->name, $input)
                : $rule->validate($value, $raw, $this->name);
            if ($message !== null) {
                $messages[] = $message;
            }
        }

        return $messages;
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
