<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes;

/**
 * Converte o valor da propriedade em enum.
 *
 * Sem `namespace`, usa o tipo declarado da propriedade. Em propriedade `array`,
 * `namespace` é obrigatório e cada item vira um case do enum informado.
 *
 * ```php
 * #[EnumAttribute]
 * public StatusEnum $status;
 *
 * #[EnumAttribute(StatusEnum::class)]
 * public array $statuses;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class EnumAttribute
{
    public function __construct(
        public string $namespace = '',
    ) {
    }
}
