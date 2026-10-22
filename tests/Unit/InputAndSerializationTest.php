<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\DtoAttribute;
use Puzl\PzDto\Attributes\EnumAttribute;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\PzDto;

final class InputAndSerializationTest extends TestCase
{
    public function test_from_aceita_json_string(): void
    {
        $dto = ProductDto::from('{"name":"Cimento","price":"10.50","kind":"bag"}');

        self::assertSame('Cimento', $dto->name);
        self::assertSame(10.5, $dto->price);
        self::assertSame(ProductKind::BAG, $dto->kind);
    }

    public function test_from_com_json_invalido_lanca(): void
    {
        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('Invalid JSON');

        ProductDto::from('não é json');
    }

    public function test_from_com_escalar_lanca(): void
    {
        $this->expectException(PzDtoException::class);

        ProductDto::from(10);
    }

    public function test_from_null_e_vazio(): void
    {
        self::assertSame('', ProductDto::from(null)->name);
        self::assertSame('', ProductDto::create(null)->name);
    }

    public function test_from_objeto_com_to_array_e_std_class(): void
    {
        $model = new class () {
            /** @return array<string, mixed> */
            public function toArray(): array
            {
                return ['name' => 'Areia', 'price' => 3];
            }
        };
        $std = (object) ['name' => 'Brita', 'price' => '4'];

        self::assertSame('Areia', ProductDto::from($model)->name);
        self::assertSame(4.0, ProductDto::from($std)->price);
    }

    public function test_from_outro_dto_preserva_objetos_e_ignora_nao_publicas(): void
    {
        $source = ProductDto::from(['name' => 'Cal', 'price' => 1, 'kind' => 'bag', 'created_at' => '2026-10-22 10:00:00', 'internal' => 'x']);

        $copy = ProductDto::from($source);

        self::assertSame(ProductKind::BAG, $copy->kind);
        self::assertSame('2026-10-22 10:00:00', $copy->created_at?->format('Y-m-d H:i:s'));
        self::assertSame('', $copy->internal());
    }

    public function test_destructuring_mescla_e_ignora_chave_ausente(): void
    {
        $dto = ProductDto::from(['name' => 'X', 'details' => ['price' => 9], 'other' => (object) ['kind' => 'box']], ['details', 'missing', 'other']);

        self::assertSame(9.0, $dto->price);
        self::assertSame(ProductKind::BOX, $dto->kind);
    }

    public function test_to_array_e_to_json_no_formato_legado(): void
    {
        $dto = ProductDto::from([
            'name' => 'Cal',
            'price' => 10,
            'kind' => 'bag',
            'supplier' => ['name' => 'ACME'],
        ]);

        self::assertSame(
            ['name' => 'Cal', 'price' => 10.0, 'kind' => 'bag', 'supplier' => ['name' => 'ACME'], 'created_at' => null],
            $dto->toArray(),
        );
        self::assertSame('{"name":"Cal","price":10.0,"kind":"bag","supplier":{"name":"ACME"},"created_at":null}', $dto->toJson());
    }

    public function test_ida_e_volta_por_array_e_json(): void
    {
        $dto = ProductDto::from(['name' => 'Cal', 'price' => 10, 'kind' => 'box', 'created_at' => '2026-10-22 10:00:00']);

        self::assertEquals($dto, ProductDto::from($dto->toArray()));
        self::assertEquals($dto, ProductDto::from($dto->toJson()));
    }
}

enum ProductKind: string
{
    case BAG = 'bag';
    case BOX = 'box';
}

final class SupplierDto extends PzDto
{
    public string $name;
}

final class ProductDto extends PzDto
{
    public string $name;
    public float $price;
    #[EnumAttribute]
    public ?ProductKind $kind;
    #[DtoAttribute]
    public ?SupplierDto $supplier;
    public ?\DateTime $created_at;
    protected string $internal = '';

    public function internal(): string
    {
        return $this->internal;
    }
}
