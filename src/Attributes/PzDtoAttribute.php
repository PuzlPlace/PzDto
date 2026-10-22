<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes;

/**
 * Converte o valor da propriedade em DTO.
 *
 * Sem classe informada, usa o tipo declarado da propriedade. Em propriedade
 * `array`, a classe é obrigatória e cada item vira uma instância dela.
 * `class` e `namespace` são sinônimos (`namespace` é o nome herdado da FromArrayTrait).
 *
 * ```php
 * #[PzDtoAttribute]
 * public AddressDto $address;
 *
 * #[PzDtoAttribute(ItemDto::class)]
 * public array $items;
 *
 * #[PzDtoAttribute(class: ItemDto::class)]
 * public array $items;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzDtoAttribute
{
    public function __construct(
        public string $namespace = '',
        string $class = '',
    ) {
        $this->namespace = $class !== '' ? $class : $namespace;
    }
}
