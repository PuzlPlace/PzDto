<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\DtoAttribute;
use Puzl\PzDto\Attributes\EnumAttribute;
use Puzl\PzDto\Attributes\Validation\IsNotEmpty;
use Puzl\PzDto\Attributes\Validation\Max;
use Puzl\PzDto\Attributes\Validation\Required;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;

final class InheritanceAndNestingTest extends TestCase
{
    public function test_filho_de_base_do_projeto_retorna_a_propria_classe(): void
    {
        $dto = FinanceCreateDto::from(['description' => 'Aluguel', 'company_id' => '7']);

        self::assertInstanceOf(FinanceCreateDto::class, $dto);
        self::assertSame('Aluguel', $dto->description);
        self::assertSame(7, $dto->company_id);
    }

    public function test_tres_niveis_de_heranca_com_private_do_pai(): void
    {
        $dto = FinanceSpecialCreateDto::from(['description' => 'x', 'company_id' => 1, 'secret' => 'abc', 'extra' => '2']);

        self::assertInstanceOf(FinanceSpecialCreateDto::class, $dto);
        self::assertSame(2, $dto->extra);
        self::assertSame('abc', $dto->secret());
    }

    public function test_filha_pode_redeclarar_propriedade_do_pai(): void
    {
        self::assertSame('override', FinanceOverrideDto::from([])->description);
    }

    public function test_create_em_classe_abstrata_lanca(): void
    {
        $this->expectException(PzDtoException::class);

        AppBaseDto::from([]);
    }

    public function test_validacao_declarada_no_pai_vale_no_filho(): void
    {
        try {
            FinanceSpecialCreateDto::from(['company_id' => 1]);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertArrayHasKey('description', $exception->errors());
        }
    }

    public function test_dto_attribute_simples_e_lista(): void
    {
        $dto = OrderDto::from([
            'customer' => ['name' => 'Ana'],
            'items' => [['sku' => 'A', 'qty' => '2'], ['sku' => 'B', 'qty' => 1]],
        ]);

        self::assertInstanceOf(CustomerDto::class, $dto->customer);
        self::assertSame('Ana', $dto->customer->name);
        self::assertCount(2, $dto->items);
        self::assertContainsOnlyInstancesOf(ItemDto::class, $dto->items);
        self::assertSame(2, $dto->items[0]->qty);
    }

    public function test_dto_pelo_tipo_sem_atributo_e_vazio_quando_ausente(): void
    {
        $dto = OrderDto::from(['items' => []]);

        self::assertInstanceOf(CustomerDto::class, $dto->billing);
        self::assertSame('', $dto->billing->name);
        self::assertNull($dto->shipping);
        self::assertSame([], $dto->items);
    }

    public function test_dto_ja_instanciado_e_mantido(): void
    {
        $customer = CustomerDto::from(['name' => 'Ana']);

        self::assertSame($customer, OrderDto::from(['customer' => $customer])->customer);
    }

    public function test_enum_attribute_simples_lista_por_tipo_e_por_name(): void
    {
        $dto = OrderDto::from([
            'status' => '2',
            'statuses' => [1, OrderStatus::PAID, 'OPEN'],
            'typed' => 2,
            'pure' => 'BIG',
        ]);

        self::assertSame(OrderStatus::PAID, $dto->status);
        self::assertSame([OrderStatus::OPEN, OrderStatus::PAID, OrderStatus::OPEN], $dto->statuses);
        self::assertSame(OrderStatus::PAID, $dto->typed);
        self::assertSame(Size::BIG, $dto->pure);
    }

    public function test_enum_invalido_lanca_em_vez_de_cair_no_primeiro_case(): void
    {
        try {
            OrderDto::from(['status' => 99, 'statuses' => [1, 99], 'typed' => 'x']);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertEqualsCanonicalizing(['status', 'statuses.1', 'typed'], array_keys($exception->errors()));
        }
    }

    public function test_enum_nullable_com_null_ou_vazio_vira_null(): void
    {
        self::assertNull(OrderDto::from(['status' => null])->status);
        self::assertNull(OrderDto::from(['status' => ''])->status);
    }

    public function test_varios_atributos_em_qualquer_ordem(): void
    {
        $dto = MultiAttributeDto::from([
            'first' => 1,
            'last' => 2,
            'items' => [['sku' => 'A', 'qty' => 1]],
        ]);

        self::assertSame(OrderStatus::OPEN, $dto->first);
        self::assertSame(OrderStatus::PAID, $dto->last);
        self::assertInstanceOf(ItemDto::class, $dto->items[0]);

        try {
            MultiAttributeDto::from(['items' => array_fill(0, 3, ['sku' => 'A', 'qty' => 1])]);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            $errors = $exception->errors();
            self::assertArrayHasKey('first', $errors);   // IsNotEmpty depois do EnumAttribute
            self::assertArrayHasKey('last', $errors);    // IsNotEmpty antes do EnumAttribute
            self::assertArrayHasKey('items', $errors);   // Max(2)
        }
    }

    public function test_erros_de_dto_aninhado_usam_notacao_de_ponto(): void
    {
        try {
            OrderDto::from(['customer' => ['name' => ''], 'items' => [['sku' => 'A', 'qty' => 1], ['qty' => 'x']]]);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertEqualsCanonicalizing(['customer.name', 'items.1.sku', 'items.1.qty'], array_keys($exception->errors()));
        }
    }

    public function test_atributos_legados_pelo_nome_curto(): void
    {
        $dto = LegacyAttributesDto::from(['customer' => ['name' => 'Ana'], 'status' => 1]);

        self::assertInstanceOf(CustomerDto::class, $dto->customer);
        self::assertSame(OrderStatus::OPEN, $dto->status);
    }

    public function test_dto_attribute_em_array_sem_classe_lanca_erro_de_declaracao(): void
    {
        $this->expectException(PzDtoException::class);
        $this->expectExceptionMessage('needs the target class');

        BrokenDeclarationDto::from([]);
    }
}

enum OrderStatus: int
{
    case OPEN = 1;
    case PAID = 2;
}

enum Size
{
    case SMALL;
    case BIG;
}

abstract class AppBaseDto extends PzDto
{
}

class FinanceCreateDto extends AppBaseDto
{
    #[Required]
    public string $description;
    public int $company_id;
}

class FinanceSpecialCreateDto extends FinanceCreateDto
{
    public int $extra = 0;
    private string $secret = '';

    public function secret(): string
    {
        return $this->secret;
    }
}

class FinanceOverrideDto extends FinanceCreateDto
{
    public string $description = 'override';
}

final class CustomerDto extends PzDto
{
    #[Required]
    public string $name;
}

final class ItemDto extends PzDto
{
    #[Required]
    public string $sku;
    public int $qty;
}

final class OrderDto extends PzDto
{
    #[DtoAttribute]
    public CustomerDto $customer;
    public CustomerDto $billing;
    public ?CustomerDto $shipping;
    #[DtoAttribute(ItemDto::class)]
    public array $items = [];
    #[EnumAttribute]
    public ?OrderStatus $status;
    #[EnumAttribute(OrderStatus::class)]
    public array $statuses = [];
    public OrderStatus $typed = OrderStatus::OPEN;
    public Size $pure = Size::SMALL;
}

final class MultiAttributeDto extends PzDto
{
    #[EnumAttribute, IsNotEmpty]
    public ?OrderStatus $first;

    #[IsNotEmpty]
    #[EnumAttribute]
    public ?OrderStatus $last;

    #[Max(2)]
    #[DtoAttribute(ItemDto::class)]
    public array $items = [];
}

final class LegacyAttributesDto extends PzDto
{
    #[Legacy\DtoAttribute]
    public CustomerDto $customer;

    #[Legacy\EnumAttribute(namespace: OrderStatus::class)]
    public OrderStatus $status;
}

final class BrokenDeclarationDto extends PzDto
{
    #[DtoAttribute]
    public array $items;
}

namespace Puzl\PzDto\Tests\Unit\Legacy;

// Imitam App\Helpers\DtoAttribute / EnumAttribute da FromArrayTrait.
#[\Attribute]
class DtoAttribute
{
    public function __construct(public string $namespace = '')
    {
    }
}

#[\Attribute]
class EnumAttribute
{
    public function __construct(public string $namespace = '')
    {
    }
}
