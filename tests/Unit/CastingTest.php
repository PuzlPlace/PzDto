<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;

final class CastingTest extends TestCase
{
    public function test_zero_e_vazio_sao_preservados_em_nullable(): void
    {
        $dto = ScalarsDto::from(['qty' => 0, 'price' => 0.0, 'tags' => [], 'active' => false, 'name' => '']);

        self::assertSame(0, $dto->qty);
        self::assertSame(0.0, $dto->price);
        self::assertSame([], $dto->tags);
        self::assertFalse($dto->active);
        self::assertSame('', $dto->name);
    }

    public function test_null_explicito_vence_default_em_nullable(): void
    {
        self::assertNull(ScalarsDto::from(['withDefault' => null])->withDefault);
        self::assertSame(5, ScalarsDto::from([])->withDefault);
    }

    public function test_chave_ausente_usa_null_em_nullable_sem_default(): void
    {
        $dto = ScalarsDto::from([]);

        self::assertNull($dto->qty);
        self::assertNull($dto->price);
        self::assertNull($dto->tags);
    }

    public function test_nao_nullable_sem_valor_recebe_zero_do_tipo(): void
    {
        $dto = RequiredScalarsDto::from(['int' => null]);

        self::assertSame(0, $dto->int);
        self::assertSame(0.0, $dto->float);
        self::assertSame('', $dto->string);
        self::assertFalse($dto->bool);
        self::assertSame([], $dto->array);
        self::assertNull($dto->mixed);
        self::assertSame('x', $dto->stringWithDefault);
    }

    public function test_nao_nullable_com_null_explicito_usa_default(): void
    {
        self::assertSame('x', RequiredScalarsDto::from(['stringWithDefault' => null])->stringWithDefault);
    }

    public function test_strings_numericas_sao_convertidas(): void
    {
        $dto = ScalarsDto::from(['qty' => '12', 'price' => '10.5']);

        self::assertSame(12, $dto->qty);
        self::assertSame(10.5, $dto->price);
        self::assertSame(1.5, ScalarsDto::from(['price' => '1,5'])->price);
        self::assertSame(12, ScalarsDto::from(['qty' => '12.9'])->qty);
    }

    public function test_string_vazia_em_numero_vira_null(): void
    {
        $dto = ScalarsDto::from(['qty' => '', 'price' => '']);

        self::assertNull($dto->qty);
        self::assertNull($dto->price);
        self::assertSame(0, RequiredScalarsDto::from(['int' => ''])->int);
    }

    public function test_string_nao_numerica_em_int_gera_erro_de_validacao(): void
    {
        try {
            ScalarsDto::from(['qty' => '12abc', 'price' => 'abc']);
            self::fail('Deveria lançar PzDtoValidationException');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(['qty', 'price'], array_keys($exception->errors()));
            self::assertSame(ScalarsDto::class, $exception->dtoClass());
        }
    }

    public function test_bool_aceita_representacoes_comuns(): void
    {
        foreach ([true, 1, '1', 'true', 'TRUE', 'on', 'yes'] as $value) {
            self::assertTrue(ScalarsDto::from(['active' => $value])->active, var_export($value, true));
        }
        foreach ([false, 0, '0', 'false', 'off', 'no', 'abc'] as $value) {
            self::assertFalse(ScalarsDto::from(['active' => $value])->active, var_export($value, true));
        }
    }

    public function test_string_recebe_escalares_e_enums(): void
    {
        self::assertSame('10', ScalarsDto::from(['name' => 10])->name);
        self::assertSame('2', ScalarsDto::from(['name' => CastBackedEnum::B])->name);
        self::assertSame('2026-01-02 03:04:05', ScalarsDto::from(['name' => new \DateTime('2026-01-02 03:04:05')])->name);
    }

    public function test_array_recebe_objeto_com_to_array(): void
    {
        $collection = new class () {
            /** @return array<int, int> */
            public function toArray(): array
            {
                return [1, 2];
            }
        };

        self::assertSame([1, 2], ScalarsDto::from(['tags' => $collection])->tags);
    }

    public function test_propriedade_sem_tipo_recebe_valor_bruto(): void
    {
        $dto = UntypedDto::from(['anything' => ['a' => 1]]);

        self::assertSame(['a' => 1], $dto->anything);
        self::assertNull(UntypedDto::from([])->anything);
    }

    public function test_union_type_mantem_valor_do_tipo_aceito(): void
    {
        self::assertTrue(UnionDto::from(['flag' => true])->flag);
        self::assertSame(1, UnionDto::from(['flag' => 1])->flag);
        self::assertSame(1.5, UnionDto::from(['value' => 1.5])->value);
        self::assertSame('abc', UnionDto::from(['value' => 'abc'])->value);
        self::assertSame(3, UnionDto::from(['value' => 3])->value);
        self::assertSame('5', UnionDto::from(['id' => '5'])->id);
    }

    public function test_union_type_converte_para_o_primeiro_membro_que_aceita(): void
    {
        // "abc" não é int: cai no próximo membro aceito (bool).
        self::assertSame(5, UnionDto::from(['flag' => '5'])->flag);
        self::assertFalse(UnionDto::from(['flag' => 'abc'])->flag);
    }

    public function test_union_type_nao_nullable_sem_valor_recebe_zero_do_primeiro_tipo(): void
    {
        self::assertNotNull(UnionDto::from([])->id);
    }

    public function test_datas(): void
    {
        $dto = DatesDto::from([
            'date' => '2026-10-22 10:00:00',
            'immutable' => new \DateTime('2026-10-22'),
            'interface' => 1_700_000_000,
        ]);

        self::assertInstanceOf(\DateTime::class, $dto->date);
        self::assertSame('2026-10-22 10:00:00', $dto->date->format('Y-m-d H:i:s'));
        self::assertInstanceOf(\DateTimeImmutable::class, $dto->immutable);
        self::assertSame('2026-10-22', $dto->immutable->format('Y-m-d'));
        self::assertInstanceOf(\DateTimeInterface::class, $dto->interface);
        self::assertNull($dto->optional);
    }

    public function test_data_serializada_por_json_e_reconstruida(): void
    {
        $json = json_decode(json_encode(['date' => new \DateTime('2026-10-22 10:00:00', new \DateTimeZone('America/Sao_Paulo'))]), true);

        $dto = DatesDto::from(['date' => $json['date'], 'immutable' => '2026-01-01', 'interface' => '2026-01-01']);

        self::assertSame('2026-10-22 10:00:00', $dto->date->format('Y-m-d H:i:s'));
        self::assertSame('America/Sao_Paulo', $dto->date->getTimezone()->getName());
    }

    public function test_data_invalida_ou_ausente_em_nao_nullable_gera_erro(): void
    {
        try {
            DatesDto::from(['date' => 'não é data', 'optional' => 'xx']);
            self::fail('Deveria lançar PzDtoValidationException');
        } catch (PzDtoValidationException $exception) {
            self::assertEqualsCanonicalizing(['date', 'immutable', 'interface', 'optional'], array_keys($exception->errors()));
        }
    }

    public function test_readonly_protected_private_e_static(): void
    {
        $dto = VisibilityDto::from(['ro' => '1', 'prot' => '2', 'priv' => '3', 'shared' => 'x']);

        self::assertSame(1, $dto->ro);
        self::assertSame(2, $dto->prot());
        self::assertSame(3, $dto->priv());
        self::assertSame('static', VisibilityDto::$shared);
        self::assertSame(['ro' => 1], $dto->toArray());
    }

    public function test_construtor_nao_e_chamado(): void
    {
        self::assertSame('veio', WithConstructorDto::from(['value' => 'veio'])->value);
    }
}

enum CastBackedEnum: int
{
    case A = 1;
    case B = 2;
}

final class ScalarsDto extends PzDto
{
    public ?int $qty;
    public ?float $price;
    public ?array $tags;
    public ?bool $active;
    public ?string $name;
    public ?int $withDefault = 5;
}

final class RequiredScalarsDto extends PzDto
{
    public int $int;
    public float $float;
    public string $string;
    public bool $bool;
    public array $array;
    public mixed $mixed;
    public string $stringWithDefault = 'x';
}

final class UntypedDto extends PzDto
{
    public $anything;
}

final class UnionDto extends PzDto
{
    public bool|int $flag = false;
    public float|string|int $value = 0;
    public string|int $id;
}

final class DatesDto extends PzDto
{
    public \DateTime $date;
    public \DateTimeImmutable $immutable;
    public \DateTimeInterface $interface;
    public ?\DateTime $optional;
}

final class VisibilityDto extends PzDto
{
    public static string $shared = 'static';
    public readonly int $ro;
    protected int $prot;
    private int $priv;

    public function prot(): int
    {
        return $this->prot;
    }

    public function priv(): int
    {
        return $this->priv;
    }
}

final class WithConstructorDto extends PzDto
{
    public string $value = 'default';

    public function __construct()
    {
        throw new \LogicException('Construtor não deveria rodar');
    }
}
