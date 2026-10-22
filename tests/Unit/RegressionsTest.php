<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\PzDtoAttribute;
use Puzl\PzDto\Attributes\PzEnumAttribute;
use Puzl\PzDto\Attributes\Validation\PzMatches;
use Puzl\PzDto\Attributes\Validation\PzMax;
use Puzl\PzDto\Attributes\Validation\PzRequired;
use Puzl\PzDto\Attributes\Validation\PzRequiredIf;
use Puzl\PzDto\Attributes\Validation\PzRequiredWith;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Exceptions\PzDtoInputException;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;

/**
 * Regressões da análise de 2026-10-22 (tasks/analise-pzdto-2026-10-22.md).
 */
final class RegressionsTest extends TestCase
{
    protected function tearDown(): void
    {
        PzDto::useLocale('en');
        PzDto::useMessages([]);
    }

    public function test_to_array_com_enum_puro_string_binaria_e_inf(): void
    {
        $dto = RegPureDto::from(['kind' => 'BIG', 'raw' => "\xC3\x28", 'any' => INF]);

        self::assertSame(['kind' => 'BIG', 'raw' => "\xC3\x28", 'any' => INF], $dto->toArray());
        self::assertSame(['kind' => 'BIG'], $dto->only('kind'));
        self::assertFalse(RegPureDto::fromTracked(['kind' => 'BIG'])->isDirty());
        self::assertSame(['kind' => 'SMALL'], RegPureDto::fromTracked(['kind' => 'BIG'])->fill(['kind' => 'SMALL'])->getDirtyPaths());
    }

    public function test_json_encode_continua_igual_ao_to_json(): void
    {
        $dto = RegOrderDto::from(['amount' => 10, 'at' => '2026-10-22 10:00:00', 'customer' => ['name' => 'Ana']]);

        self::assertSame($dto->toJson(), json_encode($dto, JSON_PRESERVE_ZERO_FRACTION));
        self::assertSame('{"amount":10.0,"at":"2026-10-22 10:00:00","customer":{"name":"Ana","tags":[],"age":null},"items":[]}', $dto->toJson());
        self::assertStringContainsString('"amount": 10.0', $dto->toJson(JSON_PRETTY_PRINT));
    }

    public function test_new_com_mais_de_63_propriedades_e_fill(): void
    {
        $dto = new WideDto();
        $dto->fill(['p70' => 1, 'p3' => 2]);

        self::assertTrue($dto->wasProvided('p70', 'p3'));
        self::assertFalse($dto->wasProvided('p64'));
        self::assertSame(['p3', 'p70'], $dto->provided());
    }

    public function test_lista_de_dto_preserva_chaves(): void
    {
        $dto = RegOrderDto::from(['items' => ['a' => ['name' => 'x'], 'b' => ['name' => 'y']]]);

        self::assertSame(['a', 'b'], array_keys($dto->items));
        self::assertSame(['items' => ['a' => ['name' => 'x'], 'b' => ['name' => 'y']]], $dto->only('items'));

        try {
            RegOrderDto::from(['items' => ['a' => ['name' => '']]]);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(['items.a.name'], array_keys($exception->errors()));
        }
    }

    public function test_input_malformado_em_aninhado_e_erro_de_validacao(): void
    {
        foreach ([['items' => [1, 'abc']], ['customer' => 'abc'], ['customer' => 5], ['items' => ['{bad json']]] as $input) {
            try {
                RegOrderDto::from($input);
                self::fail('Deveria lançar para '.json_encode($input));
            } catch (PzDtoValidationException $exception) {
                foreach ($exception->errors() as $messages) {
                    self::assertStringContainsString('must be an array', $messages[0]);
                }
            }
        }

        self::assertSame(['items.0', 'items.1'], array_keys(self::errorsOf(fn () => RegOrderDto::from(['items' => [1, 2]]))));
    }

    public function test_input_malformado_na_raiz_continua_lancando_input_exception(): void
    {
        $this->expectException(PzDtoInputException::class);
        $this->expectException(PzDtoException::class);

        RegOrderDto::from(10);
    }

    public function test_json_null_e_igual_a_from_null(): void
    {
        self::assertSame(RegOrderDto::from(null)->toArray(), RegOrderDto::from('null')->toArray());
    }

    public function test_fill_em_readonly_ja_inicializada_vira_pz_dto_exception(): void
    {
        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('Cannot assign');

        RegReadonlyDto::from(['id' => 1])->fill(['id' => 2]);
    }

    public function test_regex_invalida_e_erro_de_declaracao(): void
    {
        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('not a valid regex');

        RegBrokenRegexDto::from(['code' => 'x']);
    }

    public function test_timestamp_vem_no_fuso_da_aplicacao(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/Sao_Paulo');
        try {
            $dto = RegOrderDto::from(['at' => 1_700_000_000]);

            self::assertSame('America/Sao_Paulo', $dto->at->getTimezone()->getName());
            self::assertSame('2023-11-14 19:13:20', $dto->toArray()['at']);
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_bool_numerico_diferente_de_zero_e_true(): void
    {
        foreach ([2, 2.5, '2', '-1'] as $value) {
            self::assertTrue(RegFlagDto::from(['on' => $value])->on, var_export($value, true));
        }
        foreach ([0, 0.0, '0', '0.0', 'off', 'abc'] as $value) {
            self::assertFalse(RegFlagDto::from(['on' => $value])->on, var_export($value, true));
        }
    }

    public function test_clone_e_profundo(): void
    {
        $original = RegOrderDto::fromTracked(['customer' => ['name' => 'Ana'], 'items' => [['name' => 'a']]]);

        $copy = clone $original;
        $copy->customer->name = 'Bia';
        $copy->items[0]->name = 'b';

        self::assertSame('Ana', $original->customer->name);
        self::assertSame('a', $original->items[0]->name);
        self::assertFalse($original->isDirty());
        self::assertTrue($copy->isDirty('customer', 'items'));
        self::assertSame(['customer.name' => 'Bia', 'items.0.name' => 'b'], $copy->getDirtyPaths());
        self::assertSame('Ana', $copy->customer->getOriginal('name'));
    }

    public function test_assert_valid_cobra_required_em_propriedade_nao_inicializada(): void
    {
        $this->expectException(PzDtoValidationException::class);

        (new RegCustomerDto())->assertValid();
    }

    public function test_uuid_nil_e_max_sao_aceitos(): void
    {
        self::assertSame(
            '00000000-0000-0000-0000-000000000000',
            RegUuidDto::from(['id' => '00000000-0000-0000-0000-000000000000'])->id,
        );
        self::assertSame(
            'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF',
            RegUuidDto::from(['id' => 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF'])->id,
        );
        self::assertCount(1, self::errorsOf(fn () => RegUuidDto::from(['id' => '550e8400'])));
    }

    public function test_mensagens_em_pt_br_e_sobrescritas(): void
    {
        PzDto::useLocale('pt_BR');

        $errors = self::errorsOf(fn () => RegCustomerDto::from(['name' => '', 'tags' => [1, 2, 3], 'age' => 'x']));
        self::assertSame(['O campo name é obrigatório.'], $errors['name']);
        self::assertSame(['O campo tags não pode ter mais que 2 itens.'], $errors['tags']);
        self::assertSame(['O campo age deve ser um número inteiro.'], $errors['age']);

        PzDto::useMessages(['required' => 'Informe :field.']);
        self::assertSame(['Informe name.'], self::errorsOf(fn () => RegCustomerDto::from(['name' => '']))['name']);

        $this->expectException(PzDtoException::class);
        PzDto::useMessages(['inexistente' => 'x']);
    }

    public function test_locale_por_callable_segue_a_requisicao(): void
    {
        $current = 'pt-BR';
        PzDto::useLocale(function () use (&$current): string {
            return $current;
        });

        self::assertSame(['O campo name é obrigatório.'], self::errorsOf(fn () => RegCustomerDto::from(['name' => '']))['name']);

        $current = 'en';
        self::assertSame(['The name field is required.'], self::errorsOf(fn () => RegCustomerDto::from(['name' => '']))['name']);

        $current = 'fr';
        self::assertSame(['The name field is required.'], self::errorsOf(fn () => RegCustomerDto::from(['name' => '']))['name'], 'desconhecido cai em en');

        self::assertSame('pt_BR', (function () { PzDto::useLocale('pt_br'); return 'pt_BR'; })());
    }

    public function test_locale_desconhecido_lanca(): void
    {
        $this->expectException(PzDtoException::class);

        PzDto::useLocale('xx');
    }

    public function test_regras_contextuais_required_if_e_required_with(): void
    {
        self::assertSame([], self::errorsOf(fn () => RegContextDto::from(['type' => 'person'])));
        self::assertSame(
            ['ein' => ['The ein field is required when type is company.']],
            self::errorsOf(fn () => RegContextDto::from(['type' => 'company'])),
        );
        self::assertSame(
            ['zip' => ['The zip field is required when street is present.']],
            self::errorsOf(fn () => RegContextDto::from(['type' => 'person', 'street' => 'Rua A'])),
        );

        // fill() enxerga o estado atual: type já era company.
        $dto = RegContextDto::from(['type' => 'company', 'ein' => '1']);
        self::assertSame(['ein'], array_keys(self::errorsOf(fn () => $dto->fill(['ein' => null]))));
    }

    public function test_alias_class_nos_atributos_de_conversao(): void
    {
        $dto = RegAliasDto::from(['items' => [['name' => 'a']], 'kinds' => ['BIG']]);

        self::assertInstanceOf(RegItemDto::class, $dto->items[0]);
        self::assertSame([RegKind::BIG], $dto->kinds);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function errorsOf(callable $callback): array
    {
        try {
            $callback();

            return [];
        } catch (PzDtoValidationException $exception) {
            return $exception->errors();
        }
    }
}

enum RegKind
{
    case SMALL;
    case BIG;
}

final class RegPureDto extends PzDto
{
    public RegKind $kind = RegKind::SMALL;
    public string $raw = '';
    public mixed $any = null;
}

final class RegItemDto extends PzDto
{
    #[PzRequired]
    public string $name;
}

final class RegCustomerDto extends PzDto
{
    #[PzRequired]
    public string $name;
    #[PzMax(2)]
    public array $tags = [];
    #[\Puzl\PzDto\Attributes\Validation\PzIsInt]
    public ?int $age;
}

final class RegOrderDto extends PzDto
{
    public float $amount = 0;
    public ?\DateTimeImmutable $at;
    #[PzDtoAttribute]
    public ?RegCustomerDto $customer;
    #[PzDtoAttribute(RegItemDto::class)]
    public array $items = [];
}

final class RegReadonlyDto extends PzDto
{
    public readonly int $id;
}

final class RegBrokenRegexDto extends PzDto
{
    #[PzMatches('/[unclosed')]
    public string $code;
}

final class RegFlagDto extends PzDto
{
    public bool $on;
}

final class RegUuidDto extends PzDto
{
    #[\Puzl\PzDto\Attributes\Validation\PzIsUUID]
    public string $id;
}

final class RegContextDto extends PzDto
{
    public string $type;
    #[PzRequiredIf('type', 'company')]
    public ?string $ein;
    public ?string $street;
    #[PzRequiredWith('street', 'city')]
    public ?string $zip;
}

final class RegAliasDto extends PzDto
{
    #[PzDtoAttribute(class: RegItemDto::class)]
    public array $items = [];
    #[PzEnumAttribute(class: RegKind::class)]
    public array $kinds = [];
}
