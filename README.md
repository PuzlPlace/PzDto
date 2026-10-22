# PzDto

Classe base para **DTOs por herança** — `class FinanceCreateDto extends PzDto` — com
cast automático, aninhamento de DTO/enum (`#[DtoAttribute]`, `#[EnumAttribute]`) e
**validações por atributo**. É o sucessor da `FromArrayTrait` do `puzl/api`, sem trait,
com os bugs conhecidos corrigidos, no padrão dos demais pacotes `Pz*`.

---

## Documentação

**[Abrir documentação completa no navegador →](https://puzlplace.github.io/PzDto/)**

Site estático (GitHub Pages) com conversão de tipos, aninhamento, validações e guia de
migração. Fonte: [`docs/index.html`](docs/index.html).

---

## Instalação

O repositório `PuzlPlace/PzDto` é **público**, mas o pacote **não está no
Packagist** — é distribuído pelo próprio repositório Git. São dois passos.

### 1. Declare o repositório

No `composer.json` da aplicação, no mesmo nível de `require`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/PuzlPlace/PzDto.git"
    }
]
```

Já tem outros pacotes `Pz*`? É só acrescentar mais um objeto à lista existente.

### 2. Instale por versão

```bash
composer require puzl/pzdto:^1.0
```

Resultado no `require`:

```json
"puzl/pzdto": "^1.0"
```

Não há Service Provider nem config: basta estender `PzDto`.

### Escolhendo a constraint

Cada release publicado é uma tag semver — veja as
[Releases](https://github.com/PuzlPlace/PzDto/releases).

| Constraint | Resolve para |
|------------|--------------|
| `^1.0` | última `1.x`. **Recomendado**: pega correções e recursos novos, nunca uma major com breaking change. |
| `~1.2.0` | última `1.2.x`. Só correções de patch, sem subir de minor. |
| `v1.2.3` | exatamente essa tag. Reprodutível ao extremo; nenhuma correção chega sozinha. |
| `dev-production` | topo da branch, sem versão. Modo legado — sem rastreabilidade, evite. |

Atualizar depois:

```bash
composer update puzl/pzdto
```

### Versionamento

Não há versão escrita em lugar nenhum deste pacote — o `composer.json` **não tem**
campo `version` de propósito. A fonte é a tag do Git.

Toda entrega na branch `production` que passa no CI gera automaticamente a próxima
tag *patch* e o release correspondente, com notas montadas a partir dos commits
(`.github/workflows/tests.yml`, job `Release`). Mudanças de *minor* e *major* são
deliberadas: criam-se à mão uma vez, e o autoincremento continua a partir delas.

---

## Compatibilidade

- PHP `^8.1`
- Sem dependência de framework. Com Laravel instalado, reconhece Eloquent Model,
  Collection e paginator (testado com Laravel 13; APIs usadas existem desde o 10)

---

## Uso

```php
use Puzl\PzDto\PzDto;
use Puzl\PzDto\Attributes\DtoAttribute;
use Puzl\PzDto\Attributes\EnumAttribute;
use Puzl\PzDto\Attributes\Validation\{Required, Length, Min};

abstract class BaseDto extends PzDto {}

class FinanceCreateDto extends BaseDto
{
    #[Required, Length(max: 120)]
    public string $description;

    #[Min(0)]
    public float $amount;

    #[EnumAttribute]
    public FinanceTypeEnum $type;

    #[DtoAttribute(FinanceInstallmentDto::class)]
    public array $installments = [];
}

$dto = FinanceCreateDto::from($request->validated()); // instância de FinanceCreateDto
$dto->toArray();
```

- `from()` aceita array, Model (`toArray()`), stdClass, outro DTO ou JSON; `from($dados, ['chave'])` mescla `$dados['chave']` na raiz.
- `toArray()`/`toJson()`/`json_encode($dto)` trazem **sempre** todas as propriedades
  públicas. Campo nunca preenchido (ex.: `new` + atribuição parcial) sai com o que o
  `create()` daria sem a chave: default; `null` se nullable; senão o zero do tipo
  (`0`, `0.0`, `''`, `false`, `[]`, DTO vazio); enum/data sem default → `null`.
- Rastreamento, no estilo do `isDirty()` do Eloquent:
  - `wasProvided('campo')`, `provided()`, `toArrayProvided()` — o que veio na entrada
    (separa "não mandou" de "mandou null"; ideal para update parcial). Sempre ligado.
  - `only(...)`, `except(...)` — `toArray()` parcial, com notação de ponto e `*` em
    qualquer profundidade: `only('description', 'items.*.sku', 'items.*.tag.name')`.
  - No estilo do `Request` do Laravel: `get('items.0.sku', $default)`,
    `wasProvided('customer.name')`, `wasProvidedAny()`, `missing()`, `filled()`,
    `anyFilled()`, `isNotFilled()`, `whenProvided()`, `whenFilled()`, `fill([...])` e
    `fillMissing([...])` (convertem, validam — tudo ou nada — e marcam como recebido).
  - `toArrayProvided()` desce nos aninhados: cada DTO entrega só o que veio para ele.
  - Dirty (opt-in, desligado por padrão): `isDirty(...)`, `isClean(...)`, `getDirty()`,
    `getOriginal(...)`, `syncOriginal()` e `getDirtyPaths()` (`['items.1.qty' => 3]`).
    DTO aninhado alterado suja o pai. Três formas
    de ligar: `$dto->trackChanges()`, `X::fromTracked($dados)` ou `#[TrackChanges]` na
    classe (vale para `from()`/`create()` dela e das filhas; não vale para `new`).
    `DateTime` mutável alterado no lugar (`->modify()`) não é detectado — prefira
    `DateTimeImmutable`.
- Eloquent:
  - `Dto::from($model)` lê a Model campo a campo (`getAttribute()`), sem passar pelo
    `$model->toArray()`: casts aplicados, datas como Carbon **no fuso da aplicação**
    (o `toArray()` da Model converteria para ISO em UTC), `$hidden` disponível,
    `$appends` e relações **já carregadas** (nunca dispara lazy loading; `order_items`
    acha `orderItems`). Prop `string` com data recebe `Y-m-d H:i:s`, `Y-m-d` (cast
    `date`) ou o formato do cast (`date:d/m/Y`).
  - `Dto::collect($models)` — lista de DTOs a partir de array, Collection ou paginator;
    erros de todos os itens juntos (`1.name`, `3.qty`).
  - Datas no `toArray()` saem como `Y-m-d H:i:s`, prontas para `$model->fill()`.
  - **Update:** `$model->update($dto->toArrayProvided())`. Nunca `toArray()`: ele traz
    todos os campos e zeraria as colunas que não vieram.
- Erros de conversão e de validação de **todos** os campos vêm juntos numa
  `PzDtoValidationException` (`->errors()` → `['items.1.qty' => ['...']]`).
- Regras: `Required`, `IsNotEmpty`, `IsString`, `IsInt`, `IsFloat`, `IsBoolean`, `Min`,
  `Max`, `Length`, `Matches`, `IsEmail`, `IsUrl`, `IsUUID`, `HasExtension`,
  `IsDateString`, `In` — ou a sua, implementando `Puzl\PzDto\Contracts\ValidationRule`.

### Migrando da `FromArrayTrait`

PzDto e `FromArrayTrait` convivem no mesmo projeto; a migração é gradual, DTO a DTO,
e o aninhamento cruzado funciona nos dois sentidos. Troque `use FromArrayTrait` por
`extends PzDto` (ou por uma base nova que estenda `PzDto`), troque os imports dos
atributos e revise as diferenças de comportamento:

| Situação | FromArrayTrait | PzDto |
|----------|----------------|-------|
| `?int` com `0` / `?array` com `[]` | `null` | `0` / `[]` |
| `?int $x = 5` com `null` explícito | `5` | `null` |
| Enum com valor inexistente | primeiro case | erro de validação |
| Filho sem repetir a trait | instancia o pai | instancia o filho |
| Union `bool\|int` com `true` | fatal | `true` |
| `"12abc"` em `int` | `12` | erro de validação |
| `"1,5"` em `float` | `1.0` | `1.5` |
| JSON inválido no `from()` | DTO vazio | `PzDtoException` |
| Data ausente em não-nullable | ano -0001 | erro de validação |
| Vários atributos na propriedade | só o primeiro | todos, em qualquer ordem |
| Validação × cast | antes | depois (regras de tipo olham o valor recebido) |
| Construtor do DTO | chamado | não chamado |
| `toArray()` com campo não preenchido | chave some | sempre presente (regra do `create()`) |
| Data no `toArray()` | `{date, timezone_type, timezone}` | `"Y-m-d H:i:s"` |
| `from($model)` com data | string ISO em UTC (`toArray()` da Model) | Carbon no fuso da aplicação |

> Não faça `BaseDto extends PzDto` enquanto houver DTOs com `use FromArrayTrait`
> herdando dele — as assinaturas conflitam. Use uma base nova durante a transição.

O passo a passo completo está no tutorial [`docs/index.html`](docs/index.html).

---

## Desenvolvimento

```bash
composer install
composer test
```

A suíte de testes usa **PHPUnit puro**, dividida em duas suítes:

- `Unit` — `tests/Unit` (conversão, herança, aninhamento, entrada/saída, regras, docs)
- `Features` — `tests/Features` (convivência com uma cópia fiel da `FromArrayTrait`
  em `tests/Fixtures/Legacy` e reprodução dos bugs antigos)

### Benchmark

```bash
composer bench
```

10 mil DTOs com 30 campos, um DTO aninhado e uma lista de 3 itens. Referência
(PHP 8.3), comparando com a versão sem rastreamento:

| Cenário | Tempo | Memória |
|---------|-------|---------|
| `from()` sem rastreamento | ~169 ms | 15,7 MB |
| `from()` com `provided` (sempre ligado) | ~176 ms (+4%) | 17,5 MB |
| `trackChanges()` em todos | +~110 ms | 32,9 MB |
| `isDirty()` em todos | ~120 ms | — |

Por isso o dirty é opt-in: listagens grandes não pagam por ele.

---

## Licença

Proprietary — uso interno Puzl.
