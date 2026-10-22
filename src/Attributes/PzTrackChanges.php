<?php

declare(strict_types=1);

namespace Puzl\PzDto\Attributes;

/**
 * Todo from()/create() da classe (e das filhas) já nasce com o rastreamento de
 * alterações ligado, como se chamasse ->trackChanges() logo depois.
 *
 * ```php
 * #[PzTrackChanges]
 * class ProductUpdateDto extends PzDto { ... }
 *
 * ProductUpdateDto::from($dados)->isDirty(); // false, sem chamar trackChanges()
 * ```
 *
 * Não vale para `new` (o PzDto não intercepta construtor): use ->trackChanges().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PzTrackChanges
{
}
