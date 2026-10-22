<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\DtoAttribute;
use Puzl\PzDto\Attributes\Validation\Min;
use Puzl\PzDto\Attributes\Validation\Required;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;

final class NestedPathsTest extends TestCase
{
    public function test_only_com_ponto_e_curinga_em_varios_niveis(): void
    {
        self::assertSame(
            [
                'description' => 'Pedido',
                'customer' => ['name' => 'Ana'],
                'items' => [
                    ['sku' => 'A', 'tag' => ['name' => 'novo']],
                    ['sku' => 'B', 'tag' => null],
                ],
            ],
            self::order()->only('items.*.tag.name', 'customer.name', 'items.*.sku', 'description'),
        );
    }

    public function test_only_de_um_item_e_de_array_comum(): void
    {
        $dto = self::order();

        self::assertSame(['items' => [1 => ['qty' => 2]]], $dto->only('items.1.qty'));
        self::assertSame(['meta' => ['origin' => 'site']], $dto->only('meta.origin'));
        self::assertSame(['items' => [['tag' => ['name' => 'novo']], ['tag' => null]]], $dto->only('items.*.tag.name'));
    }

    public function test_only_caminho_interno_inexistente_e_ignorado_e_raiz_errada_lanca(): void
    {
        self::assertSame(['customer' => []], self::order()->only('customer.inexistente'));

        $this->expectException(PzDtoException::class);
        self::order()->only('custmer.name');
    }

    public function test_except_com_ponto_e_curinga(): void
    {
        $array = self::order()->except('items.*.qty', 'items.*.tag', 'customer.document', 'meta');

        self::assertSame(['sku' => 'A'], $array['items'][0]);
        self::assertSame(['name' => 'Ana'], $array['customer']);
        self::assertArrayNotHasKey('meta', $array);
    }

    public function test_to_array_provided_desce_nos_aninhados(): void
    {
        $dto = PathOrderDto::from([
            'description' => 'Pedido',
            'customer' => ['name' => 'Ana'],
            'items' => [['sku' => 'A', 'tag' => ['name' => 'novo']], ['qty' => 3]],
        ]);

        self::assertSame(
            [
                'description' => 'Pedido',
                'customer' => ['name' => 'Ana'],
                'items' => [['sku' => 'A', 'tag' => ['name' => 'novo']], ['qty' => 3]],
            ],
            $dto->toArrayProvided(),
        );
    }

    public function test_was_provided_com_caminhos(): void
    {
        $dto = PathOrderDto::from([
            'customer' => ['name' => 'Ana', 'document' => null],
            'items' => [['sku' => 'A', 'tag' => ['name' => 'x']], ['sku' => 'B']],
            'meta' => ['origin' => 'site'],
        ]);

        self::assertTrue($dto->wasProvided('customer.name', 'customer.document'));
        self::assertTrue($dto->wasProvided('items.*.sku'));
        self::assertTrue($dto->wasProvided('items.0.tag.name'));
        self::assertTrue($dto->wasProvided('meta.origin'));
        self::assertFalse($dto->wasProvided('items.*.qty'));
        self::assertFalse($dto->wasProvided('items.1.tag'));
        self::assertFalse($dto->wasProvided('items.0.tag.color'));
        self::assertFalse($dto->wasProvided('description'));
        self::assertTrue($dto->wasProvidedAny('description', 'items.1.sku'));
        self::assertTrue($dto->missing('description'));
        self::assertFalse($dto->missing('customer.name'));
        self::assertFalse(PathOrderDto::from(['items' => []])->wasProvided('items.*.sku'));
    }

    public function test_was_provided_com_raiz_inexistente_lanca(): void
    {
        $this->expectException(PzDtoException::class);

        self::order()->wasProvided('itens.*.sku');
    }

    public function test_get_com_caminhos(): void
    {
        $dto = self::order();

        self::assertSame('Ana', $dto->get('customer.name'));
        self::assertSame(2, $dto->get('items.1.qty'));
        self::assertSame(['A', 'B'], $dto->get('items.*.sku'));
        self::assertSame(['novo', 'padrão'], $dto->get('items.*.tag.name', 'padrão'));
        self::assertSame('site', $dto->get('meta.origin'));
        self::assertSame('x', $dto->get('items.9.sku', 'x'));
        self::assertInstanceOf(PathItemDto::class, $dto->get('items.0'));
    }

    public function test_filled_any_filled_e_is_not_filled(): void
    {
        $dto = PathOrderDto::from([
            'description' => '  ',
            'customer' => ['name' => 'Ana', 'document' => null],
            'items' => [['sku' => 'A'], ['sku' => '']],
        ]);

        self::assertTrue($dto->filled('customer.name'));
        self::assertFalse($dto->filled('description'));
        self::assertFalse($dto->filled('customer.document'));
        self::assertFalse($dto->filled('items.*.sku'));
        self::assertTrue($dto->filled('items.0.sku'));
        self::assertTrue($dto->anyFilled('description', 'customer.name'));
        self::assertTrue($dto->isNotFilled('description', 'customer.document', 'meta'));
    }

    public function test_when_provided_e_when_filled(): void
    {
        $dto = PathOrderDto::from(['customer' => ['name' => 'Ana'], 'description' => '']);
        $log = [];

        $dto->whenProvided('customer.name', function (mixed $value) use (&$log) { $log[] = "nome:$value"; })
            ->whenProvided('items', fn () => $log[] = 'itens', function () use (&$log) { $log[] = 'sem itens'; })
            ->whenFilled('description', fn () => $log[] = 'descrição', function () use (&$log) { $log[] = 'descrição vazia'; });

        self::assertSame(['nome:Ana', 'sem itens', 'descrição vazia'], $log);
    }

    public function test_fill_converte_valida_e_marca_como_recebido(): void
    {
        $dto = PathOrderDto::from(['description' => 'Pedido']);

        $dto->fill(['amount' => '10,5', 'customer' => ['name' => 'Ana'], 'inexistente' => 1]);

        self::assertSame(10.5, $dto->amount);
        self::assertSame('Ana', $dto->customer?->name);
        self::assertSame(['description', 'amount', 'customer'], $dto->provided());
    }

    public function test_fill_e_tudo_ou_nada(): void
    {
        $dto = PathOrderDto::from(['description' => 'Pedido', 'amount' => 1]);

        try {
            $dto->fill(['description' => 'Outro', 'amount' => -1]);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(['amount'], array_keys($exception->errors()));
        }

        self::assertSame('Pedido', $dto->description);
        self::assertSame(1.0, $dto->amount);
    }

    public function test_fill_missing_so_preenche_o_que_nao_veio(): void
    {
        $dto = PathOrderDto::from(['description' => 'Pedido', 'amount' => null]);

        $dto->fillMissing(['description' => 'Outro', 'amount' => 5, 'meta' => ['a' => 1]]);

        self::assertSame('Pedido', $dto->description);
        self::assertSame(0.0, $dto->amount, 'amount veio (null) — fillMissing não mexe');
        self::assertSame(['a' => 1], $dto->meta);
    }

    public function test_fill_com_rastreamento_aparece_no_dirty(): void
    {
        $dto = PathOrderDto::fromTracked(['description' => 'Pedido', 'amount' => 1]);

        $dto->fill(['amount' => 2]);

        self::assertSame(['amount' => 2.0], $dto->getDirtyPaths());
    }

    public function test_get_dirty_paths_desce_em_aninhados_e_listas(): void
    {
        $dto = self::order()->trackChanges();

        $dto->amount = 15;
        $dto->customer->name = 'Bia';
        $dto->items[1]->qty = 3;
        $dto->items[0]->tag->name = 'usado';

        self::assertSame(
            ['amount' => 15.0, 'customer.name' => 'Bia', 'items.0.tag.name' => 'usado', 'items.1.qty' => 3],
            $dto->getDirtyPaths(),
        );
        self::assertSame(['amount', 'customer', 'items'], array_keys($dto->getDirty()));
    }

    public function test_get_dirty_paths_com_itens_adicionados_e_removidos(): void
    {
        $dto = self::order()->trackChanges();

        $dto->items = [$dto->items[0]];
        self::assertSame(['items.1.sku' => null, 'items.1.qty' => null, 'items.1.tag' => null], $dto->getDirtyPaths());

        $dto->syncOriginal();
        $dto->items[] = PathItemDto::from(['sku' => 'C', 'qty' => 1]);
        self::assertSame(['items.1.sku' => 'C', 'items.1.qty' => 1, 'items.1.tag' => null], $dto->getDirtyPaths());
    }

    public function test_get_dirty_paths_exige_rastreamento(): void
    {
        $this->expectException(PzDtoException::class);

        self::order()->getDirtyPaths();
    }

    private static function order(): PathOrderDto
    {
        return PathOrderDto::from([
            'description' => 'Pedido',
            'amount' => 10,
            'customer' => ['name' => 'Ana', 'document' => '123'],
            'items' => [
                ['sku' => 'A', 'qty' => 1, 'tag' => ['name' => 'novo']],
                ['sku' => 'B', 'qty' => 2],
            ],
            'meta' => ['origin' => 'site', 'campaign' => 'x'],
        ]);
    }
}

final class PathTagDto extends PzDto
{
    public string $name;
}

final class PathItemDto extends PzDto
{
    public string $sku;
    public int $qty;
    #[DtoAttribute]
    public ?PathTagDto $tag;
}

final class PathCustomerDto extends PzDto
{
    #[Required]
    public string $name;
    public ?string $document;
}

final class PathOrderDto extends PzDto
{
    public string $description;
    #[Min(0)]
    public float $amount;
    #[DtoAttribute]
    public ?PathCustomerDto $customer;
    #[DtoAttribute(PathItemDto::class)]
    public array $items = [];
    public array $meta = [];
}
