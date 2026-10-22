<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes;

/**
 * Converte o valor da propriedade em enum.
 *
 * Sem classe informada, usa o tipo declarado da propriedade. Em propriedade
 * `array`, a classe é obrigatória e cada item vira um case do enum.
 * `class` e `namespace` são sinônimos (`namespace` é o nome herdado da FromArrayTrait).
 *
 * ```php
 * #[PzEnumAttribute]
 * public StatusEnum $status;
 *
 * #[PzEnumAttribute(StatusEnum::class)]
 * public array $statuses;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PzEnumAttribute
{
    public function __construct(
        public string $namespace = '',
        string $class = '',
    ) {
        $this->namespace = $class !== '' ? $class : $namespace;
    }
}
