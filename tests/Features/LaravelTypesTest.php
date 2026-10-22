<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Features;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\PzDtoAttribute;
use Puzl\PzDto\PzDto;

/**
 * Tipos do ecossistema Laravel que aparecem em DTOs: Carbon, Collection,
 * LazyCollection e relação carregada com valor null.
 */
final class LaravelTypesTest extends TestCase
{
    public function test_propriedade_carbon_recebe_string_e_sai_formatada(): void
    {
        $dto = LaravelTypesDto::from(['at' => '2026-01-02 03:04:05', 'day' => '2026-01-02']);

        self::assertInstanceOf(Carbon::class, $dto->at);
        self::assertInstanceOf(CarbonImmutable::class, $dto->day);
        self::assertSame('2026-01-02 03:04:05', $dto->toArray()['at']);
        self::assertSame('2026-01-02 00:00:00', $dto->toArray()['day']);
    }

    public function test_propriedade_collection_e_mantida_e_serializada_como_array(): void
    {
        $dto = LaravelTypesDto::from(['tags' => new Collection(['a', 'b'])]);

        self::assertInstanceOf(Collection::class, $dto->tags);
        self::assertSame(['a', 'b'], $dto->toArray()['tags']);
        self::assertSame(['a', 'b'], LaravelTypesDto::from($dto)->tags->all(), 'from($dto) preserva o objeto');
    }

    public function test_lista_de_dtos_a_partir_de_collection_e_lazy_collection(): void
    {
        $fromCollection = LaravelTypesDto::from(['items' => new Collection([['name' => 'a'], ['name' => 'b']])]);
        $fromLazy = LaravelTypesDto::from(['items' => LazyCollection::make(static fn () => yield from [['name' => 'c']])]);

        self::assertSame(['a', 'b'], array_map(static fn (LaravelItemDto $item): string => $item->name, $fromCollection->items));
        self::assertSame('c', $fromLazy->items[0]->name);
    }

    public function test_collect_aceita_lazy_collection(): void
    {
        $dtos = LaravelItemDto::collect(LazyCollection::make(static fn () => yield from [['name' => 'a'], ['name' => 'b']]));

        self::assertCount(2, $dtos);
        self::assertSame('b', $dtos[1]->name);
    }

    public function test_relacao_carregada_com_null_conta_como_recebida(): void
    {
        $model = new LaravelTypesModel();
        $model->setRawAttributes(['id' => 1], true);
        $model->setRelation('owner', null);

        $dto = LaravelOwnerDto::from($model);

        self::assertNull($dto->owner);
        self::assertTrue($dto->wasProvided('owner'));
        self::assertFalse($dto->wasProvided('other'));
    }

    public function test_from_model_ignora_atributos_que_o_dto_nao_declara(): void
    {
        $model = new LaravelTypesModel();
        $model->setRawAttributes(['id' => 1, 'extra' => 'x'], true);

        self::assertSame(['id' => 1, 'owner' => null, 'other' => null], LaravelOwnerDto::from($model)->toArray());
    }
}

class LaravelTypesModel extends Model
{
    protected $guarded = [];
}

final class LaravelItemDto extends PzDto
{
    public string $name;
}

final class LaravelOwnerDto extends PzDto
{
    public int $id;
    #[PzDtoAttribute]
    public ?LaravelItemDto $owner;
    #[PzDtoAttribute]
    public ?LaravelItemDto $other;
}

final class LaravelTypesDto extends PzDto
{
    public ?Carbon $at;
    public ?CarbonImmutable $day;
    public ?Collection $tags;
    #[PzDtoAttribute(LaravelItemDto::class)]
    public array $items = [];
}
