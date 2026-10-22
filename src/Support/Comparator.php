<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

use Puzl\PzDto\PzDto;

/**
 * Decide se o valor atual de uma propriedade difere do original.
 *
 * O snapshot original é raso: DTO aninhado na mesma instância delega para o
 * isDirty() dele (se estiver rastreando); instâncias diferentes são comparadas pelo conteúdo.
 * DateTime mutável alterado no lugar (`->modify()`) não é detectado.
 *
 * @internal
 */
final class Comparator
{
    public static function changed(mixed $original, mixed $current): bool
    {
        if ($original === $current) {
            return self::nestedDirty($current);
        }

        if ($original instanceof \DateTimeInterface && $current instanceof \DateTimeInterface) {
            return $original->format('Y-m-d H:i:s.u e') !== $current->format('Y-m-d H:i:s.u e');
        }

        if (is_array($original) || is_array($current) || $original instanceof PzDto || $current instanceof PzDto) {
            return Exporter::value($original) !== Exporter::value($current);
        }

        if (is_object($original) && is_object($current) && ! $original instanceof \UnitEnum) {
            return $original != $current;
        }

        return true;
    }

    /**
     * Mesmo valor/instância: só muda se um DTO aninhado (direto ou em lista) mudou.
     */
    private static function nestedDirty(mixed $value): bool
    {
        if ($value instanceof PzDto) {
            return $value->isTrackingChanges() && $value->isDirty();
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (($item instanceof PzDto || is_array($item)) && self::nestedDirty($item)) {
                    return true;
                }
            }
        }

        return false;
    }
}
