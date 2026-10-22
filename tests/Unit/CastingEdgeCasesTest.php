<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\PzDtoAttribute;
use Puzl\PzDto\Attributes\PzEnumAttribute;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;

/**
 * Caminhos do Caster que não aparecem nos cenários principais: string vazia por
 * tipo, conversões menos comuns para string/int, union com DTO, listas vindas de
 * Traversable/objeto, erros de declaração e de atribuição.
 */
final class CastingEdgeCasesTest extends TestCase
{
    public function test_string_vazia_vira_null_em_data_dto_e_enum_nullable(): void
    {
        $dto = EdgeCastDto::from(['date' => '', 'sub' => '', 'kind' => '']);

        self::assertNull($dto->date);
        self::assertNull($dto->sub);
        self::assertNull($dto->kind);
    }

    public function test_string_vazia_em_bool_e_string_mantem_o_zero_do_tipo(): void
    {
        $dto = EdgeCastDto::from(['flag' => '', 'text' => '']);

        self::assertFalse($dto->flag);
        self::assertSame('', $dto->text);
    }

    public function test_string_recebe_stringable_bool_e_enum_puro(): void
    {
        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'str!';
            }
        };

        self::assertSame('str!', EdgeCastDto::from(['text' => $stringable])->text);
        self::assertSame('1', EdgeCastDto::from(['text' => true])->text);
        self::assertSame('Y', EdgeCastDto::from(['text' => EdgePure::Y])->text);
        self::assertSame('b', EdgeCastDto::from(['text' => EdgeKind::B])->text);
    }

    public function test_string_nao_aceita_array_nem_objeto_comum(): void
    {
        try {
            EdgeCastDto::from(['text' => ['a'], 'int' => new \stdClass()]);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(['text', 'int'], array_keys($exception->errors()));
        }
    }

    public function test_int_e_float_recebem_float_e_bool(): void
    {
        self::assertSame(1, EdgeCastDto::from(['int' => 1.9])->int, 'float em int trunca');
        self::assertSame(1, EdgeCastDto::from(['int' => true])->int);
        self::assertSame(1.0, EdgeCastDto::from(['float' => true])->float);
        self::assertSame(1000, EdgeCastDto::from(['int' => '1e3'])->int);
        self::assertSame(2, EdgeCastDto::from(['int' => EdgeNumber::TWO])->int, 'enum backed int pelo value');
    }

    public function test_union_com_dto_tenta_a_classe_antes_do_builtin(): void
    {
        self::assertInstanceOf(EdgeSubDto::class, EdgeCastDto::from(['union' => ['name' => 'n']])->union);
        self::assertSame('n', EdgeCastDto::from(['union' => ['name' => 'n']])->union->name);
        self::assertSame('txt', EdgeCastDto::from(['union' => 'txt'])->union);
    }

    public function test_array_recebe_std_class(): void
    {
        self::assertSame(['a' => 1], EdgeCastDto::from(['list' => (object) ['a' => 1]])->list);
    }

    public function test_lista_de_dto_aceita_generator_objeto_com_to_array_e_itens_null(): void
    {
        $generator = (static fn (): \Generator => yield from ['k' => ['name' => 'g']])();
        self::assertSame(['k'], array_keys(EdgeCastDto::from(['subs' => $generator])->subs));

        $collection = new class () {
            /** @return array<int, array<string, string>> */
            public function toArray(): array
            {
                return [['name' => 'a'], ['name' => 'b']];
            }
        };
        self::assertCount(2, EdgeCastDto::from(['subs' => $collection])->subs);

        $dto = EdgeCastDto::from(['subs' => [null, ['name' => 'a']]]);
        self::assertNull($dto->subs[0]);
        self::assertSame('a', $dto->subs[1]->name);
    }

    public function test_lista_recebendo_escalar_e_erro_de_validacao(): void
    {
        try {
            EdgeCastDto::from(['subs' => 'abc', 'kinds' => 5]);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(
                ['subs' => ['The subs field must be an array.'], 'kinds' => ['The kinds field must be an array.']],
                $exception->errors(),
            );
        }
    }

    public function test_enum_aceita_case_de_outro_enum_pelo_value(): void
    {
        self::assertSame(EdgeKind::B, EdgeCastDto::from(['kind' => EdgeOtherKind::B])->kind);
    }

    public function test_lista_de_enums_mistura_value_name_e_case(): void
    {
        self::assertSame(
            [EdgeKind::A, EdgeKind::B, EdgeKind::A],
            EdgeCastDto::from(['kinds' => ['a', 'B', EdgeKind::A]])->kinds,
        );
    }

    public function test_data_aceita_instancia_de_outra_classe_e_preserva_fuso(): void
    {
        $source = new \DateTimeImmutable('2026-10-22 10:00:00', new \DateTimeZone('America/Sao_Paulo'));

        $dto = EdgeCastDto::from(['date' => $source]);

        self::assertInstanceOf(\DateTime::class, $dto->date);
        self::assertSame('2026-10-22 10:00:00', $dto->date->format('Y-m-d H:i:s'));
        self::assertSame('America/Sao_Paulo', $dto->date->getTimezone()->getName());
    }

    public function test_atribuicao_impossivel_vira_pz_dto_exception_com_contexto(): void
    {
        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('Cannot assign '.EdgeCastDto::class.'::$iterator');

        EdgeCastDto::from(['iterator' => 'x']);
    }

    public function test_enum_attribute_apontando_para_nao_enum_lanca_na_declaracao(): void
    {
        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('is not an enum');

        EdgeBadEnumDto::from([]);
    }

    public function test_dto_attribute_apontando_para_classe_sem_from_lanca_na_declaracao(): void
    {
        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('has no static from()');

        EdgeBadDtoDto::from([]);
    }

    public function test_to_array_exporta_std_class_json_serializable_e_enum_puro_em_mixed(): void
    {
        $serializable = new class () implements \JsonSerializable {
            /** @return array<string, int> */
            public function jsonSerialize(): array
            {
                return ['n' => 1];
            }
        };

        $dto = EdgeCastDto::from(['any' => (object) ['a' => EdgePure::Y, 'b' => $serializable, 'c' => new \DateTime('2026-01-01')]]);

        self::assertSame(['a' => 'Y', 'b' => ['n' => 1], 'c' => '2026-01-01 00:00:00'], $dto->toArray()['any']);
    }
}

enum EdgeKind: string
{
    case A = 'a';
    case B = 'b';
}

enum EdgeOtherKind: string
{
    case B = 'b';
}

enum EdgeNumber: int
{
    case TWO = 2;
}

enum EdgePure
{
    case X;
    case Y;
}

final class EdgeSubDto extends PzDto
{
    public string $name = '';
}

final class EdgeCastDto extends PzDto
{
    public ?\DateTime $date;
    public ?EdgeSubDto $sub;
    public ?EdgeKind $kind;
    public bool $flag = true;
    public string $text = '';
    public int $int = 0;
    public float $float = 0;
    public EdgeSubDto|string $union = '';
    public array $list = [];
    public mixed $any = null;
    public ?\Iterator $iterator;
    #[PzDtoAttribute(EdgeSubDto::class)]
    public array $subs = [];
    #[PzEnumAttribute(EdgeKind::class)]
    public array $kinds = [];
}

final class EdgeBadEnumDto extends PzDto
{
    #[PzEnumAttribute(EdgeSubDto::class)]
    public mixed $x;
}

final class EdgeBadDtoDto extends PzDto
{
    #[PzDtoAttribute(\stdClass::class)]
    public mixed $x;
}
