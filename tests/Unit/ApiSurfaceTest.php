<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puzl\PzDto\Attributes\PzDtoAttribute;
use Puzl\PzDto\Exceptions\PzDtoException;
use Puzl\PzDto\Exceptions\PzDtoInputException;
use Puzl\PzDto\Exceptions\PzDtoValidationException;
use Puzl\PzDto\PzDto;
use Puzl\PzDto\Support\Messages;

/**
 * Bordas da API pública que os cenários principais não exercitam: caminhos,
 * fill() com objeto, from($dto), guardas de nome inválido, mensagens e exceções.
 */
final class ApiSurfaceTest extends TestCase
{
    protected function tearDown(): void
    {
        PzDto::translateMessagesUsing(null);
    }

    public function test_get_except_e_is_dirty_rejeitam_propriedade_inexistente_ou_nao_publica(): void
    {
        $dto = SurfaceDto::fromTracked(['name' => 'a']);

        foreach (['nope', 'internal'] as $field) {
            foreach (['get', 'except', 'isDirty', 'getOriginal'] as $method) {
                try {
                    $dto->{$method}($field);
                    self::fail("{$method}('{$field}') deveria lançar");
                } catch (PzDtoException $exception) {
                    self::assertStringContainsString('has no public property', $exception->getMessage());
                }
            }
        }
    }

    public function test_was_provided_com_curinga_no_fim_e_dentro_de_array_comum(): void
    {
        $dto = SurfaceDto::from(['items' => [['name' => 'a']], 'meta' => ['a' => ['b' => 1]]]);

        self::assertTrue($dto->wasProvided('items.*'));
        self::assertFalse(SurfaceDto::from(['items' => []])->wasProvided('items.*'));
        self::assertTrue($dto->wasProvided('meta.a.b'));
        self::assertFalse($dto->wasProvided('meta.z'));
        self::assertFalse($dto->wasProvided('meta.a.b.c'), 'escalar não tem filhos');
    }

    public function test_get_e_filled_em_array_comum_e_std_class(): void
    {
        $dto = SurfaceDto::from(['meta' => ['a' => ['b' => 1], 'obj' => (object) ['x' => 'y'], 'blank' => '  ']]);

        self::assertSame(1, $dto->get('meta.a.b'));
        self::assertSame('y', $dto->get('meta.obj.x'));
        self::assertSame('padrão', $dto->get('meta.obj.nope', 'padrão'));
        self::assertTrue($dto->filled('meta.obj.x'));
        self::assertFalse($dto->filled('meta.blank'));
        self::assertSame([], $dto->get('items.*'), 'lista vazia com curinga → lista vazia');
    }

    public function test_fill_aceita_objeto_e_fill_missing_ignora_o_que_veio(): void
    {
        $dto = SurfaceDto::from(['name' => 'a']);

        $dto->fill((object) ['amount' => '7']);
        $dto->fillMissing((object) ['name' => 'b', 'meta' => ['k' => 1]]);

        self::assertSame(7.0, $dto->amount);
        self::assertSame('a', $dto->name);
        self::assertSame(['k' => 1], $dto->meta);
        self::assertSame(['name', 'amount', 'meta'], $dto->provided());
    }

    public function test_from_outro_dto_marca_todas_as_publicas_como_recebidas(): void
    {
        $source = SurfaceDto::from(['name' => 'a']);

        $copy = SurfaceDto::from($source);
        $partial = SurfaceDto::from($source->toArrayProvided());

        self::assertSame(['name', 'amount', 'items', 'meta'], $copy->provided());
        self::assertSame(['name'], $partial->provided(), 'toArrayProvided() preserva o provided');
    }

    public function test_input_exception_e_filha_de_pz_dto_exception_e_nao_de_validation(): void
    {
        try {
            SurfaceDto::from('{bad');
            self::fail('Deveria lançar');
        } catch (PzDtoInputException $exception) {
            self::assertInstanceOf(PzDtoException::class, $exception);
            self::assertNotInstanceOf(PzDtoValidationException::class, $exception);
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    public function test_json_que_nao_e_objeto_lanca_input_exception(): void
    {
        $this->expectException(PzDtoInputException::class);

        SurfaceDto::from('"texto"');
    }

    public function test_resumo_da_validation_exception(): void
    {
        self::assertSame('only one [X]', (new PzDtoValidationException(['a' => ['only one']], 'X'))->getMessage());
        self::assertSame(
            'first (and 1 more error) [X]',
            (new PzDtoValidationException(['a' => ['first'], 'b' => ['second']], 'X'))->getMessage(),
        );
        self::assertSame('Invalid data. [X]', (new PzDtoValidationException([], 'X'))->getMessage());
    }

    public function test_mensagens_substituem_placeholders_maiores_primeiro_e_bool(): void
    {
        self::assertSame(
            'x 1 2 true',
            Messages::make('x :min :minimum :flag', 'required', 'f', ['min' => 1, 'minimum' => 2, 'flag' => true]),
        );
    }

    public function test_tradutor_nao_recebe_mensagens_padrao(): void
    {
        PzDto::translateMessagesUsing(static fn (string $key): string => 'TRANS');

        try {
            SurfaceDto::from(['amount' => 'abc']);
            self::fail('Deveria lançar');
        } catch (PzDtoValidationException $exception) {
            self::assertSame(['The amount field must be a number.'], $exception->errors()['amount']);
        }
    }

    public function test_dirty_compara_objetos_comuns_por_conteudo(): void
    {
        $dto = SurfaceDto::fromTracked(['meta' => ['o' => (object) ['a' => 1]]]);

        $dto->meta = ['o' => (object) ['a' => 1]];
        self::assertFalse($dto->isDirty('meta'));

        $dto->meta = ['o' => (object) ['a' => 2]];
        self::assertTrue($dto->isDirty('meta'));
    }

    public function test_get_original_sem_campo_devolve_todas_as_publicas(): void
    {
        $dto = SurfaceDto::fromTracked(['name' => 'a', 'amount' => 1]);
        $dto->name = 'b';

        self::assertSame(['name' => 'a', 'amount' => 1.0, 'items' => [], 'meta' => []], $dto->getOriginal());
    }

    public function test_sync_original_liga_o_rastreamento(): void
    {
        $dto = SurfaceDto::from(['name' => 'a']);
        self::assertFalse($dto->isTrackingChanges());

        $dto->syncOriginal();
        self::assertTrue($dto->isTrackingChanges());
        self::assertTrue($dto->isClean());
    }

    public function test_clone_sem_rastreamento_e_com_readonly(): void
    {
        $dto = SurfaceReadonlyDto::from(['id' => 1, 'sub' => ['name' => 'a']]);

        $copy = clone $dto;
        $copy->sub->name = 'b';

        self::assertSame(1, $copy->id);
        self::assertSame('a', $dto->sub->name);
        self::assertFalse($copy->isTrackingChanges());
    }

    public function test_blank_nao_valida_e_nao_marca_recebidos(): void
    {
        $dto = SurfaceRequiredDto::blank();

        self::assertSame('', $dto->name);
        self::assertSame([], $dto->provided());
        $this->expectException(PzDtoValidationException::class);
        SurfaceRequiredDto::create([]);
    }

    public function test_collect_com_generator_preserva_chaves(): void
    {
        $generator = (static fn (): \Generator => yield from ['x' => ['name' => 'a'], 'y' => ['name' => 'b']])();

        $dtos = SurfaceDto::collect($generator);

        self::assertSame(['x', 'y'], array_keys($dtos));
        self::assertSame('b', $dtos['y']->name);
    }
}

final class SurfaceItemDto extends PzDto
{
    public string $name = '';
}

final class SurfaceDto extends PzDto
{
    public string $name = '';
    public float $amount = 0;
    #[PzDtoAttribute(SurfaceItemDto::class)]
    public array $items = [];
    public array $meta = [];
    protected string $internal = '';
}

final class SurfaceReadonlyDto extends PzDto
{
    public readonly int $id;
    public SurfaceItemDto $sub;
}

final class SurfaceRequiredDto extends PzDto
{
    #[\Puzl\PzDto\Attributes\Validation\PzRequired]
    public string $name;
}
