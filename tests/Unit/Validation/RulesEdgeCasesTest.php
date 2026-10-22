<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\Validation\PzHasExtension;
use Puzl\PzDto\Attributes\Validation\PzIn;
use Puzl\PzDto\Attributes\Validation\PzIsDateString;
use Puzl\PzDto\Attributes\Validation\PzIsDateStringFormat;
use Puzl\PzDto\Attributes\Validation\PzLength;
use Puzl\PzDto\Attributes\Validation\PzMax;
use Puzl\PzDto\Attributes\Validation\PzMin;
use Puzl\PzDto\Attributes\Validation\PzRequired;
use Puzl\PzDto\Attributes\Validation\PzRequiredIf;
use Puzl\PzDto\Attributes\Validation\PzRequiredWith;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;

/**
 * Opções e ramos das regras que o data provider principal não cobre.
 */
final class RulesEdgeCasesTest extends TestCase
{
    public function test_required_falha_com_array_vazio_e_passa_com_zero_e_false(): void
    {
        self::assertSame(['req'], array_keys(self::errorsOf(['req' => []])));
        self::assertSame([], self::errorsOf(['req' => 0]));
        self::assertSame([], self::errorsOf(['req' => false]));
    }

    public function test_in_estrito_e_in_sobre_array(): void
    {
        self::assertSame([], self::errorsOf(['inLoose' => '1']));
        self::assertSame(['inStrict'], array_keys(self::errorsOf(['inStrict' => '1'])));
        self::assertSame([], self::errorsOf(['inArr' => ['a', 'b']]));
        self::assertSame(['inArr'], array_keys(self::errorsOf(['inArr' => ['a', 'z']])));
    }

    public function test_has_extension_sem_lista_exige_alguma_extensao(): void
    {
        self::assertSame([], self::errorsOf(['anyExt' => 'nota.pdf']));
        self::assertSame(
            ['anyExt' => ['The anyExt field must have a file extension.']],
            self::errorsOf(['anyExt' => 'nota']),
        );
    }

    public function test_is_date_string_flexible_e_limite_invalido(): void
    {
        self::assertSame([], self::errorsOf(['flex' => 'next monday']));
        self::assertSame(['flex'], array_keys(self::errorsOf(['flex' => 'xx'])));

        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('bound "nope"');
        EdgeBoundDto::from(['date' => '2026-01-01']);
    }

    public function test_required_if_compara_enum_e_valor_frouxo(): void
    {
        self::assertSame(['needsB'], array_keys(self::errorsOf(['kind' => 'b'])));
        self::assertSame([], self::errorsOf(['kind' => 'b', 'needsB' => 'x']));
        self::assertSame([], self::errorsOf(['kind' => 'a']));
        self::assertSame(['needsOne'], array_keys(self::errorsOf(['count' => '1'])), '"1" casa com 1');
    }

    public function test_required_with_so_cobra_quando_algum_dos_outros_veio_preenchido(): void
    {
        self::assertSame([], self::errorsOf(['street' => '', 'city' => null]));
        self::assertSame(
            ['zip' => ['The zip field is required when city is present.']],
            self::errorsOf(['city' => 'SP']),
        );
    }

    public function test_max_em_string_conta_caracteres_e_min_em_array_conta_itens(): void
    {
        self::assertSame(
            ['maxStr' => ['The maxStr field must not be greater than 3 characters.']],
            self::errorsOf(['maxStr' => 'abcd']),
        );
        self::assertSame(
            ['minArr' => ['The minArr field must be at least 2 items.']],
            self::errorsOf(['minArr' => [1]]),
        );
    }

    public function test_erros_seguem_a_ordem_de_declaracao_das_regras(): void
    {
        self::assertSame(
            ['The order field must be at least 2 characters.', 'The order field must be at least 5 characters.'],
            self::errorsOf(['order' => 'a'])['order'],
        );
    }

    public function test_regra_contextual_enxerga_estado_atual_no_assert_valid(): void
    {
        $dto = EdgeRulesDto::from(['req' => 'ok', 'kind' => 'a']);
        $dto->assertValid();

        $dto->kind = EdgeRuleKind::B;

        try {
            $dto->assertValid();
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(['needsB'], array_keys($exception->errors()));
        }
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, array<int, string>>
     */
    private static function errorsOf(array $input): array
    {
        try {
            EdgeRulesDto::from($input + ['req' => 'ok']);

            return [];
        } catch (PzDtoValidationException $exception) {
            return $exception->errors();
        }
    }
}

enum EdgeRuleKind: string
{
    case A = 'a';
    case B = 'b';
}

final class EdgeRulesDto extends PzDto
{
    #[PzRequired]
    public mixed $req;
    #[PzIn([1, 2])]
    public mixed $inLoose;
    #[PzIn([1, 2], strict: true)]
    public mixed $inStrict;
    #[PzIn(['a', 'b'])]
    public ?array $inArr;
    #[PzHasExtension]
    public ?string $anyExt;
    #[PzIsDateString(PzIsDateStringFormat::FLEXIBLE)]
    public ?string $flex;
    public ?EdgeRuleKind $kind;
    #[PzRequiredIf('kind', EdgeRuleKind::B)]
    public ?string $needsB;
    public ?int $count;
    #[PzRequiredIf('count', 1)]
    public ?string $needsOne;
    public ?string $street;
    public ?string $city;
    #[PzRequiredWith('street', 'city')]
    public ?string $zip;
    #[PzMax(3)]
    public ?string $maxStr;
    #[PzMin(2)]
    public ?array $minArr;
    #[PzLength(min: 2), PzMin(5)]
    public ?string $order;
}

final class EdgeBoundDto extends PzDto
{
    #[PzIsDateString(min: 'nope')]
    public ?string $date;
}
