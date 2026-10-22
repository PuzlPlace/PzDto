<?php

declare(strict_types=1);

namespace Puzl\PzDto\Support;

/**
 * Caminhos em notação de ponto com curinga (`items.*.tag.name`) sobre arrays já
 * serializados (toArray()). Funciona igual para DTO, lista de DTO, DTO legado e
 * array comum, porque nesse ponto tudo já é array.
 *
 * @internal
 */
final class Path
{
    /**
     * @return array<int, string>
     */
    public static function segments(string $path): array
    {
        return explode('.', $path);
    }

    /**
     * Mantém só os caminhos informados. Caminho que não existe é ignorado; lista
     * percorrida por `*` mantém todos os índices.
     *
     * @param array<mixed> $data
     * @param array<int, string> $paths
     *
     * @return array<mixed>
     */
    public static function only(array $data, array $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            self::copy($data, self::segments($path), $result);
        }

        return self::reorder($result, $data);
    }

    /**
     * Remove os caminhos informados.
     *
     * @param array<mixed> $data
     * @param array<int, string> $paths
     *
     * @return array<mixed>
     */
    public static function except(array $data, array $paths): array
    {
        foreach ($paths as $path) {
            self::remove($data, self::segments($path));
        }

        return $data;
    }

    /**
     * Achata em `['items.0.qty' => 3]`. Array vazio é folha.
     *
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value) && $value !== []) {
                $result += self::flatten($value, $path);
            } else {
                $result[$path] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $segments
     * @param array<mixed> $target
     */
    private static function copy(mixed $source, array $segments, array &$target): void
    {
        $segment = array_shift($segments);

        if ($segment === '*') {
            if (! is_array($source)) {
                return;
            }
            foreach ($source as $key => $item) {
                if ($segments === []) {
                    $target[$key] = $item;
                    continue;
                }
                if (! isset($target[$key]) || ! is_array($target[$key])) {
                    $target[$key] = [];
                }
                if (is_array($item)) {
                    self::copy($item, $segments, $target[$key]);
                }
            }

            return;
        }

        if (! is_array($source) || ! array_key_exists($segment, $source)) {
            return;
        }

        $value = $source[$segment];
        if ($segments === [] || ! is_array($value)) {
            // Fim do caminho, ou o pai é null/escalar: entrega o que existe.
            $target[$segment] = $value;

            return;
        }

        if (! isset($target[$segment]) || ! is_array($target[$segment])) {
            $target[$segment] = [];
        }
        self::copy($value, $segments, $target[$segment]);
    }

    /**
     * @param array<mixed> $data
     * @param array<int, string> $segments
     */
    private static function remove(array &$data, array $segments): void
    {
        $segment = array_shift($segments);

        if ($segment === '*') {
            foreach (array_keys($data) as $key) {
                if ($segments === []) {
                    unset($data[$key]);
                } elseif (is_array($data[$key])) {
                    self::remove($data[$key], $segments);
                }
            }

            return;
        }

        if (! array_key_exists($segment, $data)) {
            return;
        }
        if ($segments === []) {
            unset($data[$segment]);
        } elseif (is_array($data[$segment])) {
            self::remove($data[$segment], $segments);
        }
    }

    /**
     * Resultado na ordem do original (a ordem dos caminhos pedidos não importa).
     *
     * @param array<mixed> $result
     * @param array<mixed> $source
     *
     * @return array<mixed>
     */
    private static function reorder(array $result, array $source): array
    {
        $ordered = [];
        foreach ($source as $key => $value) {
            if (! array_key_exists($key, $result)) {
                continue;
            }
            $ordered[$key] = is_array($result[$key]) && is_array($value)
                ? self::reorder($result[$key], $value)
                : $result[$key];
        }

        return $ordered;
    }
}
