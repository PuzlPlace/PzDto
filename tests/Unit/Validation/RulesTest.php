<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\EnumAttribute;
use Puzl\PzDto\Attributes\Validation\HasExtension;
use Puzl\PzDto\Attributes\Validation\In;
use Puzl\PzDto\Attributes\Validation\IsBoolean;
use Puzl\PzDto\Attributes\Validation\IsDateString;
use Puzl\PzDto\Attributes\Validation\IsDateStringFormat;
use Puzl\PzDto\Attributes\Validation\IsEmail;
use Puzl\PzDto\Attributes\Validation\IsFloat;
use Puzl\PzDto\Attributes\Validation\IsInt;
use Puzl\PzDto\Attributes\Validation\IsNotEmpty;
use Puzl\PzDto\Attributes\Validation\IsString;
use Puzl\PzDto\Attributes\Validation\IsUrl;
use Puzl\PzDto\Attributes\Validation\IsUUID;
use Puzl\PzDto\Attributes\Validation\Length;
use Puzl\PzDto\Attributes\Validation\Matches;
use Puzl\PzDto\Attributes\Validation\Max;
use Puzl\PzDto\Attributes\Validation\Min;
use Puzl\PzDto\Attributes\Validation\Required;
use Puzl\PzDto\Contracts\ValidationRule;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;

final class RulesTest extends TestCase
{
    protected function tearDown(): void
    {
        PzDto::translateMessagesUsing(null);
    }

    /**
     * @return array<string, array{string, mixed, bool}>
     */
    public static function cases(): array
    {
        return [
            'required ok' => ['required', 'x', true],
            'required ausente' => ['required', null, false],
            'required em branco' => ['required', '  ', false],
            'not empty ok' => ['notEmpty', 'a', true],
            'not empty zero' => ['notEmpty', 0, false],
            'is string ok' => ['isString', 'a', true],
            'is string int' => ['isString', 10, false],
            'is int ok' => ['isInt', 5, true],
            'is int string numérica' => ['isInt', '5', true],
            'is int lixo' => ['isInt', '12abc', false],
            'is int decimal' => ['isInt', '1.5', false],
            'is float ok' => ['isFloat', '1.5', true],
            'is float vírgula' => ['isFloat', '1,5', true],
            'is float int' => ['isFloat', 2, true],
            'is float lixo' => ['isFloat', '1.5abc', false],
            'is boolean ok' => ['isBoolean', 'false', true],
            'is boolean int' => ['isBoolean', 1, true],
            'is boolean inválido' => ['isBoolean', 'talvez', false],
            'min número ok' => ['minNumber', 10, true],
            'min número abaixo' => ['minNumber', 9, false],
            'max número acima' => ['maxNumber', 101, false],
            'min string' => ['minString', 'ab', false],
            'max array' => ['maxArray', [1, 2, 3], false],
            'length ok' => ['length', 'abc', true],
            'length curta' => ['length', 'a', false],
            'length multibyte' => ['length', 'ãéí', true],
            'length longa' => ['length', 'abcdef', false],
            'matches ok' => ['matches', '12345-678', true],
            'matches falha' => ['matches', '12345678', false],
            'email ok' => ['email', 'a@b.com', true],
            'email falha' => ['email', 'a@', false],
            'url ok' => ['url', 'https://puzl.com.br', true],
            'url falha' => ['url', 'puzl', false],
            'uuid ok' => ['uuid', '550e8400-e29b-41d4-a716-446655440000', true],
            'uuid falha' => ['uuid', '550e8400', false],
            'extensão ok' => ['extension', 'nota.PDF', true],
            'extensão falha' => ['extension', 'nota.exe', false],
            'data ok' => ['date', '2026-10-22', true],
            'data inválida' => ['date', '2026-02-30', false],
            'data antes do min' => ['date', '2019-12-31', false],
            'data depois do max' => ['date', '2031-01-01', false],
            'datetime ok' => ['dateTime', '2026-10-22 10:00:00', true],
            'datetime falha' => ['dateTime', '2026-10-22', false],
            'iso ok' => ['iso', '2026-10-22T10:00:00-03:00', true],
            'iso falha' => ['iso', '22/10/2026', false],
            'in ok' => ['in', 'a', true],
            'in falha' => ['in', 'z', false],
            'in enum ok' => ['inEnum', 1, true],
            'in enum falha' => ['inEnum', 2, false],
        ];
    }

    #[DataProvider('cases')]
    public function test_regra(string $field, mixed $value, bool $valid): void
    {
        try {
            RulesDto::from([$field => $value] + ['required' => 'x', 'notEmpty' => 'x']);
            self::assertTrue($valid, "{$field} deveria falhar com ".var_export($value, true));
        } catch (PzDtoValidationException $exception) {
            self::assertFalse($valid, "{$field} deveria passar: ".json_encode($exception->errors()));
            self::assertSame([$field], array_keys($exception->errors()));
        }
    }

    public function test_regras_opcionais_ignoram_null_e_ausencia(): void
    {
        $dto = RulesDto::from(['required' => 'x', 'notEmpty' => 'x']);

        self::assertNull($dto->email);
    }

    public function test_junta_erros_de_todos_os_campos(): void
    {
        try {
            SignupDto::from(['email' => 'x', 'name' => 'a', 'age' => 10]);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame([
                'name' => ['The name field must be at least 3 characters.'],
                'email' => ['The email field must be a valid email address.'],
                'age' => ['The age field must be at least 18.'],
                'password' => ['The password field is required.', 'The password field must not be empty.'],
            ], $exception->errors());
            self::assertStringStartsWith('The name field must be at least 3 characters. (and 4 more errors)', $exception->getMessage());
            self::assertInstanceOf(\InvalidArgumentException::class, $exception);
        }
    }

    public function test_mensagem_customizada_com_placeholders(): void
    {
        try {
            CustomMessageDto::from(['code' => 'ab']);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(['code' => ['Código code precisa de 3 a 5 caracteres']], $exception->errors());
        }
    }

    public function test_tradutor_recebe_mensagem_customizada(): void
    {
        PzDto::translateMessagesUsing(fn (string $key, array $replace): string => "trans({$key}):{$replace['field']}:{$replace['min']}");

        try {
            CustomMessageDto::from(['code' => 'ab']);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(['code' => ['trans(Código :field precisa de :min a :max caracteres):code:3']], $exception->errors());
        }
    }

    public function test_regra_customizada_do_projeto(): void
    {
        self::assertSame('12345678909', CustomRuleDto::from(['document' => '12345678909'])->document);

        $this->expectException(PzDtoValidationException::class);
        $this->expectExceptionMessage('CPF inválido');

        CustomRuleDto::from(['document' => '123']);
    }

    public function test_validacao_roda_depois_do_cast(): void
    {
        // "20" vira 20 antes do Min; "5" passa no IsInt (valor bruto tolerante).
        self::assertSame(20, SignupDto::from(['name' => 'Ana', 'email' => 'a@b.com', 'age' => '20', 'password' => 'x'])->age);
    }

    public function test_assert_valid_revalida_estado_atual(): void
    {
        $dto = SignupDto::from(['name' => 'Ana', 'email' => 'a@b.com', 'age' => 20, 'password' => 'x']);
        $dto->assertValid();

        $dto->age = 5;

        $this->expectException(PzDtoValidationException::class);
        $dto->assertValid();
    }
}

enum RuleEnum: int
{
    case ONE = 1;
    case TWO = 2;
}

final class RulesDto extends PzDto
{
    #[Required]
    public ?string $required = 'x';
    #[IsNotEmpty]
    public mixed $notEmpty = 'x';
    #[IsString]
    public mixed $isString;
    #[IsInt]
    public ?int $isInt;
    #[IsFloat]
    public ?float $isFloat;
    #[IsBoolean]
    public ?bool $isBoolean;
    #[Min(10)]
    public ?int $minNumber;
    #[Max(100)]
    public ?int $maxNumber;
    #[Min(3)]
    public ?string $minString;
    #[Max(2)]
    public ?array $maxArray;
    #[Length(min: 2, max: 5)]
    public ?string $length;
    #[Matches('/^\d{5}-\d{3}$/')]
    public ?string $matches;
    #[IsEmail]
    public ?string $email;
    #[IsUrl]
    public ?string $url;
    #[IsUUID]
    public ?string $uuid;
    #[HasExtension(['pdf', '.xml'])]
    public ?string $extension;
    #[IsDateString(min: '2020-01-01', max: '2030-12-31')]
    public ?string $date;
    #[IsDateString(IsDateStringFormat::DATETIME)]
    public ?string $dateTime;
    #[IsDateString(IsDateStringFormat::ISO8601)]
    public ?string $iso;
    #[In(['a', 'b'])]
    public ?string $in;
    #[EnumAttribute, In([RuleEnum::ONE])]
    public ?RuleEnum $inEnum;
}

final class SignupDto extends PzDto
{
    #[Required, Length(min: 3)]
    public string $name;
    #[Required, IsEmail]
    public string $email;
    #[IsInt, Min(18)]
    public int $age;
    #[Required]
    #[IsNotEmpty]
    public string $password;
}

final class CustomMessageDto extends PzDto
{
    #[Length(min: 3, max: 5, message: 'Código :field precisa de :min a :max caracteres')]
    public string $code;
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Cpf implements ValidationRule
{
    public function validate(mixed $value, mixed $raw, string $field): ?string
    {
        return is_string($value) && strlen($value) === 11 ? null : 'CPF inválido';
    }
}

final class CustomRuleDto extends PzDto
{
    #[Cpf]
    public string $document;
}
