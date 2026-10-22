<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Features;

require_once dirname(__DIR__).'/Fixtures/Legacy/FromArrayTrait.php';

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\PzDtoAttribute;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;
use Puzl\PzDto\Tests\Fixtures\Legacy\DtoAttribute as LegacyDtoAttribute;
use Puzl\PzDto\Tests\Fixtures\Legacy\FromArrayTrait;

/**
 * PzDto e FromArrayTrait no mesmo projeto, durante a migração gradual.
 *
 * Também reproduz os bugs do relatório `analise-fromarraytrait.md`: a trait legada
 * continua com o comportamento antigo e o PzDto com o corrigido.
 */
final class LegacyCoexistenceTest extends TestCase
{
    public function test_pz_dto_aninha_dto_legado(): void
    {
        $dto = NewOrderDto::from(['customer' => ['name' => 'Ana', 'age' => '30'], 'legacyItems' => [['sku' => 'A']]]);

        self::assertInstanceOf(LegacyCustomerDto::class, $dto->customer);
        self::assertSame(30, $dto->customer->age);
        self::assertInstanceOf(LegacyItemDto::class, $dto->legacyItems[0]);
    }

    public function test_dto_legado_aninha_pz_dto(): void
    {
        $dto = LegacyOrderDto::from(['address' => ['zip' => '01001-000'], 'typed' => ['zip' => '02002-000']]);

        self::assertInstanceOf(NewAddressDto::class, $dto->address);
        self::assertSame('01001-000', $dto->address->zip);
        self::assertInstanceOf(NewAddressDto::class, $dto->typed);
    }

    public function test_validacao_do_pz_dto_aninhado_em_legado_propaga(): void
    {
        $this->expectException(PzDtoValidationException::class);

        LegacyOrderDto::from(['address' => ['zip' => '123']]);
    }

    public function test_conversao_entre_os_dois_mundos(): void
    {
        $legacy = LegacyCustomerDto::from(['name' => 'Ana', 'age' => 30]);
        $new = NewCustomerDto::from($legacy);
        $back = LegacyCustomerDto::from($new);

        self::assertSame(['name' => 'Ana', 'age' => 30], $new->toArray());
        self::assertSame('Ana', $back->name);
        self::assertSame(30, $back->age);
    }

    public function test_bugs_do_relatorio_legado_x_pz_dto(): void
    {
        $input = ['qty' => 0, 'price' => 0.0, 'tags' => [], 'def' => null, 'sid' => 'abc'];

        $legacy = LegacyReportDto::from($input);
        self::assertNull($legacy->qty);       // bug 1
        self::assertNull($legacy->price);     // bug 1
        self::assertNull($legacy->tags);      // bug 1
        self::assertSame(5, $legacy->def);    // bug 2

        $new = NewReportDto::from($input);
        self::assertSame(0, $new->qty);
        self::assertSame(0.0, $new->price);
        self::assertSame([], $new->tags);
        self::assertNull($new->def);
        self::assertSame('abc', $new->sid);
    }

    public function test_bug_self_heranca_sem_trait(): void
    {
        self::assertSame(LegacyReportDto::class, get_class(@LegacyChildReportDto::from(['extra' => 1]))); // bug 4 (cria propriedade dinâmica no pai)
        self::assertSame(NewChildReportDto::class, get_class(NewChildReportDto::from(['extra' => 1])));
    }

    public function test_bug_union_bool_int(): void
    {
        self::assertTrue(NewReportDto::from(['flag' => true])->flag); // bug 5: na trait é fatal
    }

    public function test_bug_enum_invalido(): void
    {
        self::assertSame(ReportEnum::A, LegacyReportDto::from(['e' => 99])->e); // bug 3

        $this->expectException(PzDtoValidationException::class);
        NewReportDto::from(['e' => 99]);
    }
}

enum ReportEnum: int
{
    case A = 1;
    case B = 2;
}

// ---- legado (FromArrayTrait) -------------------------------------------------

class LegacyCustomerDto
{
    use FromArrayTrait;

    public string $name;
    public int $age;
}

class LegacyItemDto
{
    use FromArrayTrait;

    public string $sku;
}

class LegacyOrderDto
{
    use FromArrayTrait;

    #[LegacyDtoAttribute]
    public NewAddressDto $address;
    public ?NewAddressDto $typed;
}

class LegacyReportDto
{
    use FromArrayTrait;

    public ?int $qty;
    public ?float $price;
    public ?array $tags;
    public ?ReportEnum $e;
    public ?int $def = 5;
    public string|int $sid;
}

class LegacyChildReportDto extends LegacyReportDto
{
    public int $extra;
}

// ---- novo (PzDto) ------------------------------------------------------------

final class NewAddressDto extends PzDto
{
    #[\Puzl\PzDto\Attributes\Validation\PzMatches('/^\d{5}-\d{3}$/')]
    public string $zip;
}

final class NewCustomerDto extends PzDto
{
    public string $name;
    public int $age;
}

final class NewOrderDto extends PzDto
{
    #[PzDtoAttribute]
    public LegacyCustomerDto $customer;
    #[DtoAttribute(LegacyItemDto::class)]
    public array $legacyItems = [];
}

class NewReportDto extends PzDto
{
    public ?int $qty;
    public ?float $price;
    public ?array $tags;
    public ?ReportEnum $e;
    public ?int $def = 5;
    public string|int $sid = '';
    public bool|int $flag = false;
}

class NewChildReportDto extends NewReportDto
{
    public int $extra;
}
