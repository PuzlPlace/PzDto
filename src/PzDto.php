<?php

declare(strict_types=1);

namespace Puzl\PzDto;

use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\Support\Bits;
use Puzl\PzDto\Support\Caster;
use Puzl\PzDto\Support\CastError;
use Puzl\PzDto\Support\ClassMetadata;
use Puzl\PzDto\Support\Comparator;
use Puzl\PzDto\Support\Messages;
use Puzl\PzDto\Support\ModelReader;
use Puzl\PzDto\Support\Path;

/**
 * Base dos DTOs: basta estender (direto ou por uma base do projeto).
 *
 * ```php
 * abstract class BaseDto extends PzDto {}
 *
 * class FinanceCreateDto extends BaseDto
 * {
 *     #[Required, Length(max: 120)]
 *     public string $description;
 *
 *     #[DtoAttribute(FinanceItemDto::class)]
 *     public array $items;
 * }
 *
 * $dto = FinanceCreateDto::from($request->validated());
 * ```
 */
abstract class PzDto implements \JsonSerializable
{
    /** Campos cuja chave veio no from()/create() — bit por propriedade. */
    private int|string $pzProvided = 0;

    /**
     * Valores públicos guardados por trackChanges()/syncOriginal(), na ordem de
     * ClassMetadata::publicNames. null = rastreamento desligado (padrão: custa
     * memória e tempo em listas grandes, por isso é opt-in).
     *
     * @var array<int, mixed>|null
     */
    private ?array $pzOriginal = null;

    /**
     * Carregar dados através de array | Model | stdClass | DTO | string JSON.
     * Estrutura dos dados precisa ser compatível com a classe.
     *
     * @param array<string, mixed>|object|string|null $values
     * @param array<int, string>|null $destructuring Chaves cujo conteúdo (array) é mesclado na raiz
     *
     * @throws PzDtoValidationException quando algum campo não converte ou não passa nas regras
     * @throws PzDtoException quando a entrada não pode ser lida
     */
    public static function from(mixed $values, ?array $destructuring = null): static
    {
        $source = $values;
        $values = self::normalizeInput($values);

        foreach ($destructuring ?? [] as $key) {
            $nested = ModelReader::isModel($source) && ! array_key_exists($key, $values)
                ? ModelReader::readKey($source, $key)[1]
                : ($values[$key] ?? null);
            if (is_object($nested)) {
                $nested = self::normalizeInput($nested);
            }
            if (is_array($nested)) {
                $values = array_merge($values, $nested);
            }
        }

        return static::create($values);
    }

    /**
     * Lista de DTOs a partir de array, Collection, LazyCollection ou paginator
     * (de Models, arrays, objetos…). As chaves são preservadas.
     *
     * Os erros de validação de todos os itens vêm juntos, com o índice no
     * caminho: `['0.name' => [...], '3.qty' => [...]]`.
     *
     * @param iterable<mixed> $items
     *
     * @return array<int|string, static>
     *
     * @throws PzDtoValidationException
     */
    public static function collect(iterable $items): array
    {
        $result = [];
        $errors = [];

        foreach ($items as $key => $item) {
            try {
                $result[$key] = static::from($item);
            } catch (PzDtoValidationException $exception) {
                foreach ($exception->errors() as $path => $messages) {
                    $errors[Caster::join((string) $key, (string) $path)] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw new PzDtoValidationException($errors, static::class);
        }

        return $result;
    }

    /**
     * from() com o rastreamento de alterações já ligado (= from()->trackChanges()).
     *
     * @param array<string, mixed>|object|string|null $values
     * @param array<int, string>|null $destructuring
     *
     * @throws PzDtoValidationException
     * @throws PzDtoException
     */
    public static function fromTracked(mixed $values, ?array $destructuring = null): static
    {
        $instance = static::from($values, $destructuring);

        return $instance->isTrackingChanges() ? $instance : $instance->trackChanges();
    }

    /**
     * Instanciar a própria classe, convertendo e validando cada propriedade.
     *
     * @param array<string, mixed>|null $values
     *
     * @throws PzDtoValidationException
     */
    public static function create(?array $values): static
    {
        return static::build($values ?? [], true);
    }

    /**
     * Instância "vazia", sem regras de validação: o que um DTO aninhado
     * não-nullable recebe quando a chave não veio (mesmo papel do `from([])` legado).
     * Propriedades sem "zero" possível (enum, data) ficam não inicializadas.
     */
    public static function blank(): static
    {
        return static::build([], false);
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function build(array $values, bool $validate): static
    {
        $metadata = ClassMetadata::for(static::class);

        if ($metadata->reflection->isAbstract()) {
            throw new PzDtoException(sprintf('Cannot create abstract DTO %s.', static::class));
        }

        $instance = $metadata->reflection->newInstanceWithoutConstructor();
        $errors = [];
        $bitsAsInt = $metadata->bitsAsInt;
        $provided = $bitsAsInt ? 0 : Bits::empty(count($metadata->properties));
        $index = -1;

        foreach ($metadata->properties as $name => $property) {
            $index++;
            $present = array_key_exists($name, $values);
            $raw = $present ? $values[$name] : null;
            if ($present) {
                // Caminho comum (até 63 propriedades) inline: é o laço mais quente do pacote.
                $provided = $bitsAsInt ? $provided | (1 << $index) : Bits::set($provided, $index);
            }

            try {
                $value = Caster::cast($property, $raw, $present);
            } catch (CastError $error) {
                foreach ($error->errors as $path => $messages) {
                    $errors[Caster::join($name, (string) $path)] = $messages;
                }
                continue;
            }

            foreach ($validate ? $property->rules : [] as $rule) {
                $message = $rule->validate($value, $raw, $name);
                if ($message !== null) {
                    $errors[$name][] = $message;
                }
            }

            try {
                $metadata->write($instance, $property, $value);
            } catch (\TypeError $error) {
                throw new PzDtoException(
                    sprintf('Cannot assign %s::$%s: %s', static::class, $name, $error->getMessage()),
                    previous: $error,
                );
            }
        }

        if ($validate && $errors !== []) {
            throw new PzDtoValidationException($errors, static::class);
        }

        $instance->pzProvided = $provided;
        if ($metadata->tracksChanges) {
            $instance->syncOriginal();
        }

        return $instance;
    }

    /**
     * Revalida o estado atual do objeto (útil depois de alterar propriedades à mão).
     *
     * @throws PzDtoValidationException
     */
    public function assertValid(): void
    {
        $metadata = ClassMetadata::for(static::class);
        $errors = [];

        foreach ($metadata->properties as $name => $property) {
            if ($property->rules === []) {
                continue;
            }

            [$initialized, $value] = $metadata->read($this, $property);
            if (! $initialized) {
                continue;
            }

            foreach ($property->rules as $rule) {
                $message = $rule->validate($value, $value, $name);
                if ($message !== null) {
                    $errors[$name][] = $message;
                }
            }
        }

        if ($errors !== []) {
            throw new PzDtoValidationException($errors, static::class);
        }
    }

    /**
     * Propriedades públicas como array (enums viram o value; DTOs aninhados, arrays).
     *
     * Datas saem como string `Y-m-d H:i:s` (fuso do próprio objeto).
     *
     * Sempre traz todas as públicas. Campo nunca preenchido (ex.: `new` com atribuição
     * parcial) sai com o que o create() daria sem a chave: default; null se nullable;
     * senão o zero do tipo (0, 0.0, '', false, [], DTO vazio); enum/data → null.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return json_decode($this->toJson(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $metadata = ClassMetadata::for(static::class);
        $data = [];

        foreach ($metadata->publicNames as $name) {
            $data[$name] = self::exportValue($this->currentValue($metadata, $name));
        }

        return $data;
    }

    /**
     * As chaves de todos os caminhos informados vieram no from()/create()/fill()?
     * Separa "não mandou" de "mandou null" (ex.: update parcial / PATCH).
     *
     * Aceita notação de ponto e `*`: `wasProvided('customer.name', 'items.*.sku')`.
     * Com `*`, a lista precisa ter itens e todos precisam ter a chave.
     */
    public function wasProvided(string ...$paths): bool
    {
        foreach ($paths as $path) {
            if (! $this->pathProvided($this, Path::segments($path), $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Algum dos caminhos veio na entrada?
     */
    public function wasProvidedAny(string ...$paths): bool
    {
        foreach ($paths as $path) {
            if ($this->wasProvided($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Algum dos caminhos não veio na entrada? (= ! wasProvided(...))
     */
    public function missing(string ...$paths): bool
    {
        return ! $this->wasProvided(...$paths);
    }

    /**
     * Nomes dos campos (primeiro nível) cuja chave veio no from()/create()/fill().
     *
     * @return array<int, string>
     */
    public function provided(): array
    {
        $metadata = ClassMetadata::for(static::class);

        return array_values(array_filter(
            array_keys($metadata->properties),
            fn (string $name): bool => Bits::has($this->pzProvided, $metadata->indexes[$name]),
        ));
    }

    /**
     * toArray() só com o que veio na entrada — em todos os níveis: cada DTO
     * aninhado (direto ou em lista) entrega só os campos que vieram para ele.
     *
     * @return array<string, mixed>
     */
    public function toArrayProvided(): array
    {
        return self::jsonRoundTrip($this->exportProvided());
    }

    /**
     * Valor pelo caminho (`customer.name`, `items.0.sku`, `items.*.sku` → lista).
     * Devolve o valor real (DTO, enum, DateTime), não serializado.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $metadata = ClassMetadata::for(static::class);
        $segments = Path::segments($path);
        $metadata->assertPublic($segments[0]);

        return self::dig($this, $segments, $default);
    }

    /**
     * Todos os caminhos vieram e estão preenchidos (não null, não string em branco,
     * não array vazio)? Com `*`, todos os itens precisam estar preenchidos.
     */
    public function filled(string ...$paths): bool
    {
        foreach ($paths as $path) {
            if (! $this->wasProvided($path) || ! self::isFilledValue($this->get($path), str_contains($path, '*'))) {
                return false;
            }
        }

        return true;
    }

    public function anyFilled(string ...$paths): bool
    {
        foreach ($paths as $path) {
            if ($this->filled($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nenhum dos caminhos está preenchido?
     */
    public function isNotFilled(string ...$paths): bool
    {
        return ! $this->anyFilled(...$paths);
    }

    /**
     * Executa `$callback($valor, $dto)` se o caminho veio na entrada; senão `$default($dto)`.
     *
     * @param callable(mixed, static): mixed $callback
     * @param (callable(static): mixed)|null $default
     */
    public function whenProvided(string $path, callable $callback, ?callable $default = null): static
    {
        if ($this->wasProvided($path)) {
            $callback($this->get($path), $this);
        } elseif ($default !== null) {
            $default($this);
        }

        return $this;
    }

    /**
     * Executa `$callback($valor, $dto)` se o caminho está preenchido; senão `$default($dto)`.
     *
     * @param callable(mixed, static): mixed $callback
     * @param (callable(static): mixed)|null $default
     */
    public function whenFilled(string $path, callable $callback, ?callable $default = null): static
    {
        if ($this->filled($path)) {
            $callback($this->get($path), $this);
        } elseif ($default !== null) {
            $default($this);
        }

        return $this;
    }

    /**
     * Atribui valores convertendo e validando como no from(), e marca os campos
     * como recebidos. Tudo ou nada: com erro, nada é alterado. Chaves que não são
     * propriedades são ignoradas (como no from()).
     *
     * @param array<string, mixed>|object $values
     *
     * @throws PzDtoValidationException
     */
    public function fill(array|object $values): static
    {
        return $this->assign(is_array($values) ? $values : self::normalizeInput($values), false);
    }

    /**
     * fill() só dos campos que ainda não vieram na entrada.
     *
     * @param array<string, mixed>|object $values
     *
     * @throws PzDtoValidationException
     */
    public function fillMissing(array|object $values): static
    {
        return $this->assign(is_array($values) ? $values : self::normalizeInput($values), true);
    }

    /**
     * toArray() só com os caminhos informados. Aceita notação de ponto e `*`:
     *
     * ```php
     * $dto->only('description', 'items.*.sku', 'items.*.tag.name');
     * ```
     *
     * O primeiro segmento precisa ser propriedade pública (erro de digitação lança);
     * dentro dele, caminho inexistente é ignorado.
     *
     * @return array<string, mixed>
     */
    public function only(string ...$paths): array
    {
        $this->assertRootPaths($paths);

        return Path::only($this->toArray(), $paths);
    }

    /**
     * toArray() sem os caminhos informados (notação de ponto e `*`, como no only()).
     *
     * @return array<string, mixed>
     */
    public function except(string ...$paths): array
    {
        $this->assertRootPaths($paths);

        return Path::except($this->toArray(), $paths);
    }

    /**
     * Liga o rastreamento de alterações: o estado atual vira o original (inclusive
     * nos DTOs aninhados). Necessário antes de isDirty()/getDirty()/getOriginal(),
     * a menos que a classe tenha #[TrackChanges] ou o DTO venha de fromTracked().
     *
     * ```php
     * $dto = FinanceUpdateDto::from($dados)->trackChanges();
     * ```
     */
    public function trackChanges(): static
    {
        return $this->syncOriginal();
    }

    public function isTrackingChanges(): bool
    {
        return $this->pzOriginal !== null;
    }

    /**
     * Algum campo público (ou algum dos informados) mudou desde trackChanges()/syncOriginal()?
     * DTOs aninhados contam: se um filho mudou, o campo do pai está sujo.
     *
     * @throws PzDtoException se o rastreamento não foi ligado com trackChanges()
     */
    public function isDirty(string ...$fields): bool
    {
        if ($fields === []) {
            return $this->getDirty() !== [];
        }

        $this->assertTracking();
        $metadata = ClassMetadata::for(static::class);
        foreach ($fields as $field) {
            $metadata->assertPublic($field);
            if ($this->fieldChanged($metadata, $field)) {
                return true;
            }
        }

        return false;
    }

    public function isClean(string ...$fields): bool
    {
        return ! $this->isDirty(...$fields);
    }

    /**
     * Campos públicos alterados, com o valor atual.
     *
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $this->assertTracking();
        $metadata = ClassMetadata::for(static::class);
        $dirty = [];

        foreach ($metadata->publicNames as $name) {
            if ($this->fieldChanged($metadata, $name)) {
                $dirty[$name] = $this->currentValue($metadata, $name);
            }
        }

        return $dirty;
    }

    /**
     * O que mudou, em notação de ponto e já serializado, descendo nos DTOs
     * aninhados e listas: `['amount' => 15.0, 'items.1.qty' => 3]`.
     * Chave que deixou de existir aparece com null.
     *
     * @return array<string, mixed>
     */
    public function getDirtyPaths(): array
    {
        $this->assertTracking();

        $original = Path::flatten(self::jsonRoundTrip($this->exportOriginal()));
        $current = Path::flatten($this->toArray());

        $dirty = [];
        foreach ($current as $path => $value) {
            if (! array_key_exists($path, $original) || $original[$path] !== $value) {
                $dirty[$path] = $value;
            }
        }
        foreach (array_diff_key($original, $current) as $path => $value) {
            $dirty[$path] = null;
        }

        return $dirty;
    }

    /**
     * Valor original de um campo público, ou todos (`[campo => valor]`).
     */
    public function getOriginal(?string $field = null): mixed
    {
        $this->assertTracking();
        $metadata = ClassMetadata::for(static::class);

        if ($field !== null) {
            $metadata->assertPublic($field);

            return $this->originalValue($metadata, $field);
        }

        $original = [];
        foreach ($metadata->publicNames as $name) {
            $original[$name] = $this->originalValue($metadata, $name);
        }

        return $original;
    }

    /**
     * O estado atual vira o original (inclusive nos DTOs aninhados). Liga o
     * rastreamento se ainda não estava ligado.
     */
    public function syncOriginal(): static
    {
        $metadata = ClassMetadata::for(static::class);
        $original = [];

        foreach ($metadata->publicNames as $name) {
            $value = $this->currentValue($metadata, $name);
            self::syncNested($value);
            $original[] = $value;
        }
        $this->pzOriginal = $original;

        return $this;
    }

    private function fieldChanged(ClassMetadata $metadata, string $name): bool
    {
        return Comparator::changed($this->originalValue($metadata, $name), $this->currentValue($metadata, $name));
    }

    private function originalValue(ClassMetadata $metadata, string $name): mixed
    {
        return $this->pzOriginal[$metadata->publicIndexes[$name]];
    }

    private function assertTracking(): void
    {
        if ($this->pzOriginal === null) {
            throw new PzDtoException(sprintf(
                'Change tracking is off for %s: call ->trackChanges() first (e.g. %s::from($data)->trackChanges()).',
                static::class,
                substr((string) strrchr('\\'.static::class, '\\'), 1),
            ));
        }
    }

    /**
     * Valor atual; campo não inicializado vale o mesmo que no toArray().
     */
    private function currentValue(ClassMetadata $metadata, string $name): mixed
    {
        [$initialized, $value] = $metadata->read($this, $metadata->properties[$name]);

        return $initialized ? $value : Caster::absent($metadata->properties[$name]);
    }

    /**
     * Datas saem como `Y-m-d H:i:s` no fuso do próprio objeto (pronto para
     * `$model->fill()`), e não como `{date, timezone_type, timezone}`.
     */
    private static function exportValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_array($value)) {
            return array_map(self::exportValue(...), $value);
        }

        return $value;
    }

    private static function syncNested(mixed $value): void
    {
        if ($value instanceof self) {
            $value->syncOriginal();
        } elseif (is_array($value)) {
            array_walk($value, static fn (mixed $item) => self::syncNested($item));
        }
    }

    /**
     * @param array<int, string> $paths
     */
    private function assertRootPaths(array $paths): void
    {
        $metadata = ClassMetadata::for(static::class);
        foreach ($paths as $path) {
            $metadata->assertPublic(Path::segments($path)[0]);
        }
    }

    /**
     * @param array<int, string> $segments
     */
    private function pathProvided(mixed $node, array $segments, string $path): bool
    {
        $segment = array_shift($segments);

        if ($node instanceof self) {
            $metadata = ClassMetadata::for($node::class);
            if (! isset($metadata->indexes[$segment])) {
                if ($node === $this) {
                    throw new PzDtoException(sprintf('%s has no property "%s".', static::class, $segment));
                }

                return false;
            }
            if (! Bits::has($node->pzProvided, $metadata->indexes[$segment])) {
                return false;
            }
            [, $value] = $metadata->read($node, $metadata->properties[$segment]);

            return $segments === [] || $this->pathProvided($value, $segments, $path);
        }

        if ($segment === '*') {
            if (! is_array($node) || $node === []) {
                return false;
            }
            foreach ($node as $item) {
                if ($segments !== [] && ! $this->pathProvided($item, $segments, $path)) {
                    return false;
                }
            }

            return true;
        }

        if (is_object($node) && ! $node instanceof \UnitEnum) {
            $node = get_object_vars($node);
        }
        if (! is_array($node) || ! array_key_exists($segment, $node)) {
            return false;
        }

        return $segments === [] || $this->pathProvided($node[$segment], $segments, $path);
    }

    /**
     * @param array<int, string> $segments
     */
    private static function dig(mixed $node, array $segments, mixed $default): mixed
    {
        while ($segments !== []) {
            $segment = array_shift($segments);

            if ($segment === '*') {
                if (! is_array($node)) {
                    return $default;
                }

                return array_map(
                    static fn (mixed $item): mixed => $segments === [] ? $item : self::dig($item, $segments, $default),
                    $node,
                );
            }

            if ($node instanceof self) {
                $metadata = ClassMetadata::for($node::class);
                if (! isset($metadata->publicIndexes[$segment])) {
                    return $default;
                }
                $node = $node->currentValue($metadata, $segment);
            } elseif (is_array($node) && array_key_exists($segment, $node)) {
                $node = $node[$segment];
            } elseif (is_object($node) && ! $node instanceof \UnitEnum && property_exists($node, $segment)) {
                $node = get_object_vars($node)[$segment] ?? null;
            } else {
                return $default;
            }
        }

        return $node;
    }

    private static function isFilledValue(mixed $value, bool $list): bool
    {
        if ($list && is_array($value)) {
            foreach ($value as $item) {
                if (! self::isFilledValue($item, false)) {
                    return false;
                }
            }

            return $value !== [];
        }

        return ! ($value === null || $value === [] || (is_string($value) && trim($value) === ''));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @throws PzDtoValidationException
     */
    private function assign(array $values, bool $onlyMissing): static
    {
        $metadata = ClassMetadata::for(static::class);
        $pending = [];
        $errors = [];

        foreach ($values as $name => $raw) {
            $name = (string) $name;
            if (! isset($metadata->properties[$name])) {
                continue;
            }
            if ($onlyMissing && Bits::has($this->pzProvided, $metadata->indexes[$name])) {
                continue;
            }

            $property = $metadata->properties[$name];
            try {
                $value = Caster::cast($property, $raw, true);
            } catch (CastError $error) {
                foreach ($error->errors as $path => $messages) {
                    $errors[Caster::join($name, (string) $path)] = $messages;
                }
                continue;
            }
            foreach ($property->rules as $rule) {
                $message = $rule->validate($value, $raw, $name);
                if ($message !== null) {
                    $errors[$name][] = $message;
                }
            }
            $pending[$name] = $value;
        }

        if ($errors !== []) {
            throw new PzDtoValidationException($errors, static::class);
        }

        foreach ($pending as $name => $value) {
            $metadata->write($this, $metadata->properties[$name], $value);
            $this->pzProvided = Bits::set($this->pzProvided, $metadata->indexes[$name]);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportProvided(): array
    {
        $metadata = ClassMetadata::for(static::class);
        $data = [];
        foreach ($metadata->publicNames as $name) {
            if (Bits::has($this->pzProvided, $metadata->indexes[$name])) {
                $data[$name] = self::exportProvidedValue($this->currentValue($metadata, $name));
            }
        }

        return $data;
    }

    private static function exportProvidedValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->exportProvided();
        }
        if (is_array($value)) {
            return array_map(self::exportProvidedValue(...), $value);
        }

        return self::exportValue($value);
    }

    /**
     * Estado original, descendo nos DTOs aninhados que rastreiam (cópia rasa:
     * o filho guarda o próprio original).
     *
     * @return array<string, mixed>
     */
    private function exportOriginal(): array
    {
        $metadata = ClassMetadata::for(static::class);
        $data = [];
        foreach ($metadata->publicNames as $name) {
            $data[$name] = self::exportOriginalValue($this->originalValue($metadata, $name));
        }

        return $data;
    }

    private static function exportOriginalValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->isTrackingChanges() ? $value->exportOriginal() : $value->jsonSerialize();
        }
        if (is_array($value)) {
            return array_map(self::exportOriginalValue(...), $value);
        }

        return self::exportValue($value);
    }

    /**
     * @return array<mixed>
     */
    private static function jsonRoundTrip(mixed $data): array
    {
        return json_decode(json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Registra um tradutor para as mensagens customizadas dos atributos de validação.
     *
     * ```php
     * PzDto::translateMessagesUsing(fn (string $key, array $replace) => trans($key, $replace));
     * ```
     *
     * @param (callable(string, array<string, scalar>): string)|null $translator
     */
    public static function translateMessagesUsing(?callable $translator): void
    {
        Messages::translateUsing($translator);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeInput(mixed $values): array
    {
        if ($values === null) {
            return [];
        }

        if (is_string($values)) {
            try {
                $decoded = json_decode($values, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new PzDtoException(
                    sprintf('Invalid JSON given to %s::from(): %s', static::class, $exception->getMessage()),
                    previous: $exception,
                );
            }
            if (! is_array($decoded)) {
                throw new PzDtoException(sprintf('JSON given to %s::from() must be an object or array.', static::class));
            }

            return $decoded;
        }

        if (is_array($values)) {
            return $values;
        }

        if ($values instanceof self) {
            // Só as públicas (como no toArray), preservando objetos (DateTime, enum, DTO).
            $public = [];
            foreach ((new \ReflectionObject($values))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if (! $property->isStatic() && $property->isInitialized($values)) {
                    $public[$property->getName()] = $property->getValue($values);
                }
            }

            return $public;
        }

        if (ModelReader::isModel($values)) {
            return ModelReader::read($values, ClassMetadata::for(static::class));
        }

        if (is_object($values)) {
            return method_exists($values, 'toArray') ? (array) $values->toArray() : get_object_vars($values);
        }

        throw new PzDtoException(sprintf(
            '%s::from() expects array, object or JSON string, %s given.',
            static::class,
            get_debug_type($values),
        ));
    }
}
