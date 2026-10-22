# PzDto

Classe base para **DTOs por herança** em PHP 8.1+. Você declara propriedades tipadas,
chama `::from($dados)` e recebe a instância preenchida: cada valor convertido para o
tipo declarado, DTOs e enums aninhados resolvidos, e o objeto sabendo **o que veio na
entrada** e **o que mudou depois**.

```php
use Puzl\PzDto\PzDto;
use Puzl\PzDto\Attributes\PzDtoAttribute;

enum OrderStatus: string { case OPEN = 'open'; case PAID = 'paid'; }

final class CustomerDto extends PzDto
{
    public string $name;
    public ?string $email;
}

final class OrderItemDto extends PzDto
{
    public string $sku;
    public int $qty = 1;
    public float $price;
}

final class OrderCreateDto extends PzDto
{
    public string $description;
    public float $amount;
    public OrderStatus $status = OrderStatus::OPEN;   // enum pelo tipo
    public CustomerDto $customer;                     // DTO aninhado pelo tipo

    #[PzDtoAttribute(OrderItemDto::class)]            // array: precisa dizer a classe dos itens
    public array $items = [];

    public ?\DateTimeImmutable $due_at;
}

$dto = OrderCreateDto::from($request->validated());

$dto->amount;           // "150,90" → 150.9
$dto->status;           // "paid"   → OrderStatus::PAID
$dto->items[0]->qty;    // "2"      → 2
$dto->toArray();        // pronto para JSON ou $model->fill()
```

**[Guia completo do desenvolvedor →](https://puzlplace.github.io/PzDto/)** (fonte: [`docs/index.html`](docs/index.html))

---

## Instalação

O repositório é público, mas o pacote **não está no Packagist**.

1. Declare o repositório no `composer.json` da aplicação:

   ```json
   "repositories": [
       { "type": "vcs", "url": "https://github.com/PuzlPlace/PzDto.git" }
   ]
   ```

2. Instale por versão:

   ```bash
   composer require puzl/pzdto:^1.0
   ```

Sem Service Provider, config ou comando. Requisitos: PHP `^8.1`; nenhuma dependência
obrigatória. Com Laravel presente, reconhece Eloquent Model, Collection e paginator.

Mensagens de erro em português (opcional), no `boot()` do `AppServiceProvider`:

```php
PzDto::useLocale('pt_BR');                        // fixo
PzDto::useLocale(fn () => app()->getLocale());    // segue o idioma da requisição
```

---

## O que o pacote faz

| Área | Em uma linha |
|------|--------------|
| **Entrada** | `from()` aceita array, Eloquent Model, stdClass, outro DTO ou string JSON; `collect()` para listas; `from($dados, ['chave'])` achata uma chave na raiz. |
| **Cast** | `int`, `float` (aceita `"1,5"`), `string`, `bool` (`"on"`, `"1"`, número ≠ 0), `array`, datas (`DateTime*`, Carbon), enums (value ou name), union types. Ausente → default ou "zero" do tipo; `null` em nullable fica `null`. |
| **Aninhamento** | DTO e enum resolvidos pelo tipo da propriedade. Em `array`, `#[PzDtoAttribute(X::class)]` / `#[PzEnumAttribute(X::class)]` dizem a classe dos itens. |
| **Saída** | `toArray()`, `toJson()`, `json_encode($dto)`, `only('a', 'items.*.sku')`, `except(...)`. Enum backed → `value`; enum puro → `name`; data → `Y-m-d H:i:s`. |
| **O que veio** | `wasProvided()`, `missing()`, `filled()`, `provided()`, `get('items.0.sku')`, `whenProvided()`, `fill()`, `fillMissing()`, `toArrayProvided()` — separa "não mandou" de "mandou null". |
| **O que mudou** | Opt-in: `trackChanges()`, `fromTracked()` ou `#[PzTrackChanges]`. `isDirty()`, `getDirty()`, `getDirtyPaths()`, `getOriginal()`, `syncOriginal()`. |
| **Eloquent** | `from($model)` lê campo a campo (casts aplicados, Carbon no fuso da app, accessors, relações já carregadas — nunca lazy load). `$model->update($dto->toArrayProvided())` para PATCH. |
| **Erros** | Tudo que não converte vem junto numa `PzDtoValidationException` (`->errors()` em notação de ponto: `items.1.qty`). Entrada ilegível na raiz: `PzDtoInputException`. |
| **Bônus** | Validação por atributo (`#[PzRequired]`, `#[PzLength]`, `#[PzIsEmail]`…) para quando o DTO é a única fronteira. Validação de negócio fica no FormRequest, Services e Entities. |

### Update parcial (PATCH)

```php
// PATCH /orders/1  { "status": "paid", "due_at": null }
$dto = OrderUpdateDto::from($request->validated());

$dto->wasProvided('due_at');   // true  — veio, mesmo sendo null
$dto->wasProvided('amount');   // false — não veio

$order->update($dto->toArrayProvided());   // só status e due_at
```

> Nunca use `toArray()` em update parcial: ele traz todas as propriedades e zeraria as
> colunas que não vieram.

### Model → DTO → resposta

```php
$dtos = OrderViewDto::collect(Order::with('customer', 'items')->paginate());

return response()->json(array_map(fn ($d) => $d->toArray(), $dtos));
```

---

## Migrando da `FromArrayTrait`

Troque `use FromArrayTrait;` por `extends PzDto` e os imports de `App\Helpers\DtoAttribute` /
`EnumAttribute` por `Puzl\PzDto\Attributes\PzDtoAttribute` / `PzEnumAttribute`. Os dois convivem no
mesmo projeto (aninhamento cruzado funciona nos dois sentidos) e os atributos antigos
continuam sendo reconhecidos pelo nome curto durante a transição. A tabela de diferenças de
comportamento está no [guia](https://puzlplace.github.io/PzDto/#diferencas).

> Não faça `BaseDto extends PzDto` enquanto houver filhos com a trait — as assinaturas
> conflitam. Use uma base nova durante a transição.

---

## Desenvolvimento

```bash
composer install
composer test     # PHPUnit: tests/Unit + tests/Features (Eloquent e convivência com a trait)
composer bench    # 10 mil DTOs com 30 campos, 1 aninhado e lista de 3 itens
```

### Versionamento

Não há versão escrita no pacote — a fonte é a tag do Git. Toda entrega na branch
`production` com CI verde gera a próxima tag *patch* e o release
(`.github/workflows/tests.yml`, job `Release`). Minor e major são criados à mão uma vez;
o autoincremento continua a partir deles.

| Constraint | Resolve para |
|------------|--------------|
| `^1.0` | última `1.x` — **recomendado** |
| `~1.2.0` | última `1.2.x` (só patch) |
| `v1.2.3` | exatamente essa tag |

---

## Licença

Proprietary — uso interno Puzl.
