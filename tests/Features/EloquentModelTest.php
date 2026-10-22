<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Features;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\PzDtoAttribute;
use Puzl\PzDto\Attributes\Validation\PzRequired;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;

/**
 * PzDto conversando com Eloquent Model (sem banco: atributos crus + setRelation),
 * no fuso do puzl/api.
 */
final class EloquentModelTest extends TestCase
{
    private string $previousTimezone;

    protected function setUp(): void
    {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('America/Sao_Paulo');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
    }

    public function test_le_a_model_com_casts_e_datas_no_fuso_da_aplicacao(): void
    {
        $dto = EloquentOrderDto::from(self::order());

        self::assertSame(10, $dto->id);
        self::assertSame(10.5, $dto->amount);
        self::assertTrue($dto->active);
        self::assertSame(['a' => 1], $dto->meta);
        self::assertSame(EloquentStatus::PAID, $dto->status);
        self::assertSame('2026-10-22 10:00:00', $dto->paid_at->format('Y-m-d H:i:s'));
        self::assertSame('America/Sao_Paulo', $dto->paid_at->getTimezone()->getName());
        self::assertSame('2026-10-22 09:30:00', $dto->created_at);
        self::assertSame('2026-10-25', $dto->due);
        self::assertSame('25/10/2026', $dto->label_date);
    }

    public function test_to_array_da_model_entregaria_utc(): void
    {
        // Por isso o from() não usa $model->toArray(): ele converte para ISO em UTC.
        self::assertSame('2026-10-22T13:00:00.000000Z', self::order()->toArray()['paid_at']);
    }

    public function test_hidden_appends_e_relacoes_carregadas(): void
    {
        $dto = EloquentOrderDto::from(self::order());

        self::assertSame('s3cr3t', $dto->secret);
        self::assertSame(99, $dto->total);
        self::assertSame('ORDER-10', $dto->code, 'Accessor clássico fora de $appends');
        self::assertSame('Ana!', $dto->label, 'Accessor Attribute::make fora de $appends');
        self::assertTrue($dto->wasProvided('code', 'label'));
        self::assertSame('Ana', $dto->customer?->name);
        self::assertCount(2, $dto->order_items);
        self::assertContainsOnlyInstancesOf(EloquentItemDto::class, $dto->order_items);
        self::assertSame(2, $dto->order_items[1]->qty);
        self::assertSame('2026-10-22 10:00:00', $dto->order_items[0]->created_at?->format('Y-m-d H:i:s'));
    }

    public function test_relacao_nao_carregada_nao_dispara_query_e_nao_conta_como_recebida(): void
    {
        $order = self::order();

        $dto = EloquentOrderDto::from($order);

        self::assertNull($dto->seller);
        self::assertFalse($dto->wasProvided('seller'));
        self::assertTrue($dto->wasProvided('customer', 'order_items', 'amount'));
        self::assertFalse($order->relationLoaded('seller'));
    }

    public function test_destructuring_de_relacao_da_model(): void
    {
        $dto = EloquentFlatDto::from(self::order(), ['customer']);

        self::assertSame(10.5, $dto->amount);
        self::assertSame('Ana', $dto->name);
        self::assertSame(1, $dto->id, 'Como na trait: a chave do aninhado vence a da raiz');
    }

    public function test_to_array_com_datas_em_string_volta_para_a_model(): void
    {
        $dto = EloquentOrderDto::from(self::order());

        $array = $dto->toArray();
        self::assertSame('2026-10-22 10:00:00', $array['paid_at']);
        self::assertSame('2026-10-22 10:00:00', $array['order_items'][0]['created_at']);

        $model = new EloquentOrder();
        $model->fill($dto->only('amount', 'paid_at', 'status', 'meta'));

        self::assertSame('2026-10-22 10:00:00', $model->paid_at->format('Y-m-d H:i:s'));
        self::assertSame(EloquentStatus::PAID, $model->status);
        self::assertSame(['a' => 1], $model->meta);
    }

    public function test_update_parcial_com_to_array_provided(): void
    {
        $model = self::order();
        $dto = EloquentOrderPatchDto::from(['amount' => '20.00']);

        $model->fill($dto->toArrayProvided());

        self::assertSame(['amount' => 20.0], $dto->toArrayProvided());
        self::assertSame('20.00', $model->amount);
        self::assertTrue($model->active, 'Campo não enviado não pode ser sobrescrito');
    }

    public function test_collect_de_collection_paginator_e_array(): void
    {
        $models = new Collection([self::order(), self::order(['id' => 11])]);

        $fromCollection = EloquentOrderDto::collect($models);
        self::assertCount(2, $fromCollection);
        self::assertSame(11, $fromCollection[1]->id);
        self::assertSame('2026-10-22 10:00:00', $fromCollection[1]->paid_at->format('Y-m-d H:i:s'));

        $paginator = new LengthAwarePaginator($models, 2, 15);
        self::assertCount(2, EloquentOrderDto::collect($paginator));

        $fromArray = EloquentItemDto::collect(['a' => ['qty' => '1'], 'b' => ['qty' => 2]]);
        self::assertSame(['a', 'b'], array_keys($fromArray));
        self::assertSame(1, $fromArray['a']->qty);
    }

    public function test_collect_junta_erros_com_o_indice(): void
    {
        try {
            EloquentCustomerDto::collect([['name' => 'Ana'], ['name' => ''], []]);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(['1.name', '2.name'], array_keys($exception->errors()));
        }
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function order(array $override = []): EloquentOrder
    {
        $order = new EloquentOrder();
        $order->setRawAttributes($override + [
            'id' => 10,
            'amount' => '10.50',
            'active' => 1,
            'meta' => '{"a":1}',
            'status' => 2,
            'paid_at' => '2026-10-22 10:00:00',
            'created_at' => '2026-10-22 09:30:00',
            'due' => '2026-10-25',
            'label_date' => '2026-10-25',
            'secret' => 's3cr3t',
        ], true);

        $customer = new EloquentCustomer();
        $customer->setRawAttributes(['id' => 1, 'name' => 'Ana'], true);
        $order->setRelation('customer', $customer);

        $items = [];
        foreach ([1, 2] as $qty) {
            $item = new EloquentItem();
            $item->setRawAttributes(['qty' => $qty, 'created_at' => '2026-10-22 10:00:00'], true);
            $items[] = $item;
        }
        $order->setRelation('orderItems', new Collection($items));

        return $order;
    }
}

enum EloquentStatus: int
{
    case OPEN = 1;
    case PAID = 2;
}

class EloquentOrder extends Model
{
    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $hidden = ['secret'];
    protected $appends = ['total'];
    protected $casts = [
        'amount' => 'decimal:2',
        'active' => 'boolean',
        'meta' => 'array',
        'status' => EloquentStatus::class,
        'paid_at' => 'datetime',
        'due' => 'date',
        'label_date' => 'date:d/m/Y',
    ];

    public function getTotalAttribute(): int
    {
        return 99;
    }

    public function getCodeAttribute(): string
    {
        return 'ORDER-'.$this->attributes['id'];
    }

    protected function label(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->customer->name.'!');
    }
}

class EloquentCustomer extends Model
{
    protected $guarded = [];
}

class EloquentItem extends Model
{
    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:s';
}

final class EloquentCustomerDto extends PzDto
{
    #[PzRequired]
    public string $name;
}

final class EloquentItemDto extends PzDto
{
    public int $qty;
    public ?\DateTime $created_at;
}

final class EloquentOrderDto extends PzDto
{
    public int $id;
    public float $amount;
    public bool $active;
    public array $meta;
    public EloquentStatus $status;
    public \DateTimeInterface $paid_at;
    public string $created_at;
    public string $due;
    public string $label_date;
    public string $secret;
    public int $total;
    public string $code;
    public string $label;
    #[PzDtoAttribute]
    public ?EloquentCustomerDto $customer;
    #[PzDtoAttribute]
    public ?EloquentCustomerDto $seller;
    #[PzDtoAttribute(EloquentItemDto::class)]
    public array $order_items = [];
}

final class EloquentFlatDto extends PzDto
{
    public int $id;
    public float $amount;
    public string $name;
}

final class EloquentOrderPatchDto extends PzDto
{
    public float $amount;
    public bool $active;
}
