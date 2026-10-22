<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\DtoAttribute;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\PzDto;

final class TrackingTest extends TestCase
{
    public function test_was_provided_separa_ausente_de_null(): void
    {
        $dto = TrackedDto::from(['name' => 'Ana', 'discount' => null]);

        self::assertTrue($dto->wasProvided('name'));
        self::assertTrue($dto->wasProvided('discount'));
        self::assertTrue($dto->wasProvided('name', 'discount'));
        self::assertFalse($dto->wasProvided('amount'));
        self::assertFalse($dto->wasProvided('name', 'amount'));
        self::assertSame(['name', 'discount'], $dto->provided());
    }

    public function test_provided_inclui_destructuring_e_campos_protegidos(): void
    {
        $dto = TrackedDto::from(['extra' => ['amount' => 5], 'internal' => 'x'], ['extra']);

        self::assertSame(['amount', 'internal'], $dto->provided());
        self::assertTrue($dto->wasProvided('internal'));
    }

    public function test_new_nao_tem_campos_recebidos(): void
    {
        $dto = new TrackedDto();
        $dto->name = 'Ana';

        self::assertSame([], $dto->provided());
        self::assertSame([], $dto->toArrayProvided());
    }

    public function test_to_array_provided_para_update_parcial(): void
    {
        $dto = TrackedDto::from(['name' => 'Ana', 'discount' => null, 'internal' => 'x']);

        self::assertSame(['name' => 'Ana', 'discount' => null], $dto->toArrayProvided());
    }

    public function test_only_e_except(): void
    {
        $dto = TrackedDto::from(['name' => 'Ana', 'amount' => 10]);

        self::assertSame(['name' => 'Ana', 'amount' => 10.0], $dto->only('amount', 'name'));
        self::assertSame(['name' => 'Ana', 'discount' => null, 'address' => ['city' => '']], $dto->except('amount'));
    }

    public function test_campo_inexistente_lanca(): void
    {
        $dto = TrackedDto::from([]);

        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('has no public property "nme"');

        $dto->only('nme');
    }

    public function test_campo_protegido_nao_e_aceito_em_only(): void
    {
        $this->expectException(PzDtoException::class);

        TrackedDto::from([])->only('internal');
    }

    public function test_dirty_exige_track_changes(): void
    {
        $dto = TrackedDto::from(['name' => 'Ana']);

        self::assertFalse($dto->isTrackingChanges());
        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('->trackChanges()');

        $dto->isDirty();
    }

    public function test_dirty_clean_get_dirty_e_original(): void
    {
        $dto = TrackedDto::from(['name' => 'Ana', 'amount' => 10])->trackChanges();

        self::assertTrue($dto->isTrackingChanges());
        self::assertFalse($dto->isDirty());
        self::assertTrue($dto->isClean());

        $dto->amount = 15;

        self::assertTrue($dto->isDirty());
        self::assertTrue($dto->isDirty('amount'));
        self::assertFalse($dto->isDirty('name'));
        self::assertTrue($dto->isClean('name'));
        self::assertSame(['amount' => 15.0], $dto->getDirty());
        self::assertSame(10.0, $dto->getOriginal('amount'));
        self::assertSame(15.0, $dto->amount);
        self::assertSame('Ana', $dto->getOriginal()['name']);
    }

    public function test_voltar_ao_valor_original_deixa_limpo(): void
    {
        $dto = TrackedDto::from(['amount' => 10])->trackChanges();
        $dto->amount = 20;
        $dto->amount = 10;

        self::assertFalse($dto->isDirty());
    }

    public function test_sync_original(): void
    {
        $dto = TrackedDto::from(['amount' => 10])->trackChanges();
        $dto->amount = 20;
        $dto->syncOriginal();

        self::assertFalse($dto->isDirty());
        self::assertSame(20.0, $dto->getOriginal('amount'));
    }

    public function test_dto_aninhado_alterado_suja_o_pai(): void
    {
        $dto = TrackedDto::from(['address' => ['city' => 'SP']])->trackChanges();

        $dto->address->city = 'RJ';

        self::assertTrue($dto->address->isDirty());
        self::assertTrue($dto->isDirty('address'));
        self::assertSame(['address'], array_keys($dto->getDirty()));

        $dto->syncOriginal();
        self::assertFalse($dto->isDirty());
        self::assertFalse($dto->address->isDirty());
    }

    public function test_dto_aninhado_trocado_compara_por_conteudo(): void
    {
        $dto = TrackedDto::from(['address' => ['city' => 'SP']])->trackChanges();

        $dto->address = TrackedAddressDto::from(['city' => 'SP']);
        self::assertFalse($dto->isDirty('address'));

        $dto->address = TrackedAddressDto::from(['city' => 'RJ']);
        self::assertTrue($dto->isDirty('address'));
    }

    public function test_listas_enums_e_datas(): void
    {
        $dto = TrackedListDto::from([
            'items' => [['city' => 'A'], ['city' => 'B']],
            'kind' => 'bag',
            'at' => '2026-10-22 10:00:00',
        ])->trackChanges();

        self::assertFalse($dto->isDirty());

        $dto->items[1]->city = 'C';
        self::assertTrue($dto->isDirty('items'));
        $dto->items[1]->city = 'B';
        self::assertFalse($dto->isDirty('items'));

        $dto->at = new \DateTimeImmutable('2026-10-22 10:00:00');
        self::assertFalse($dto->isDirty('at'));
        $dto->at = new \DateTimeImmutable('2026-10-23 10:00:00');
        self::assertTrue($dto->isDirty('at'));

        $dto->kind = ProductKind::BOX;
        self::assertTrue($dto->isDirty('kind'));
    }

    public function test_new_com_track_changes(): void
    {
        $dto = (new TrackedDto())->trackChanges();
        self::assertFalse($dto->isDirty());
        self::assertSame(0.0, $dto->getOriginal('amount'));

        $dto->name = 'Ana';
        self::assertSame(['name' => 'Ana'], $dto->getDirty());
    }

    public function test_estado_interno_nao_vaza(): void
    {
        $dto = TrackedDto::from(['name' => 'Ana'])->trackChanges();

        self::assertArrayNotHasKey('pzProvided', $dto->toArray());
        self::assertArrayNotHasKey('pzOriginal', $dto->toArray());
        self::assertSame(['name' => 'Ana'], array_intersect_key(TrackedDto::from($dto)->toArray(), ['name' => 1]));
    }

    public function test_mais_de_63_propriedades(): void
    {
        $values = [];
        for ($i = 1; $i <= 70; $i++) {
            $values['p'.$i] = $i;
        }
        unset($values['p5']);

        $dto = WideDto::from($values);

        self::assertTrue($dto->wasProvided('p1', 'p64', 'p70'));
        self::assertFalse($dto->wasProvided('p5'));
        self::assertCount(69, $dto->provided());
    }
}

final class TrackedAddressDto extends PzDto
{
    public string $city;
}

final class TrackedDto extends PzDto
{
    public string $name;
    public float $amount;
    public ?float $discount;
    #[DtoAttribute]
    public TrackedAddressDto $address;
    protected string $internal = '';
}

final class TrackedListDto extends PzDto
{
    #[DtoAttribute(TrackedAddressDto::class)]
    public array $items = [];
    public ProductKind $kind;
    public \DateTimeImmutable $at;
}

final class WideDto extends PzDto
{
    public ?int $p1, $p2, $p3, $p4, $p5, $p6, $p7, $p8, $p9, $p10;
    public ?int $p11, $p12, $p13, $p14, $p15, $p16, $p17, $p18, $p19, $p20;
    public ?int $p21, $p22, $p23, $p24, $p25, $p26, $p27, $p28, $p29, $p30;
    public ?int $p31, $p32, $p33, $p34, $p35, $p36, $p37, $p38, $p39, $p40;
    public ?int $p41, $p42, $p43, $p44, $p45, $p46, $p47, $p48, $p49, $p50;
    public ?int $p51, $p52, $p53, $p54, $p55, $p56, $p57, $p58, $p59, $p60;
    public ?int $p61, $p62, $p63, $p64, $p65, $p66, $p67, $p68, $p69, $p70;
}
