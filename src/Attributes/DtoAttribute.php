<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes;

/**
 * Converte o valor da propriedade em DTO.
 *
 * Sem `namespace`, usa o tipo declarado da propriedade. Em propriedade `array`,
 * `namespace` é obrigatório e cada item vira uma instância da classe informada.
 *
 * ```php
 * #[DtoAttribute]
 * public AddressDto $address;
 *
 * #[DtoAttribute(ItemDto::class)]
 * public array $items;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class DtoAttribute
{
    public function __construct(
        public string $namespace = '',
    ) {
    }
}
