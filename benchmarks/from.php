<?php
// Uso: composer bench  (ou: php benchmarks/from.php [raiz-de-outra-versão-do-pacote])
// 10 mil DTOs com 30 campos + 1 DTO aninhado + lista de 3 itens.
$root = $argv[1] ?? dirname(__DIR__);
spl_autoload_register(function (string $class) use ($root) {
    $prefix = 'Puzl\\PzDto\\';
    if (str_starts_with($class, $prefix)) {
        $file = $root.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) require $file;
    }
});

use Puzl\PzDto\PzDto;
use Puzl\PzDto\Attributes\PzDtoAttribute;
use Puzl\PzDto\Attributes\PzEnumAttribute;

enum BStatus: int { case A = 1; case B = 2; }
final class BItem extends PzDto { public string $sku; public int $qty; public float $price; }
final class BAddress extends PzDto { public string $street; public string $city; public ?string $zip; }
final class BRow extends PzDto {
    public int $f1; public int $f2; public int $f3; public int $f4; public int $f5;
    public string $s1; public string $s2; public string $s3; public string $s4; public string $s5;
    public float $d1; public float $d2; public float $d3; public float $d4; public float $d5;
    public ?int $n1; public ?int $n2; public ?string $n3; public ?string $n4; public ?float $n5;
    public bool $b1; public bool $b2; public array $a1; public mixed $m1; public ?\DateTimeImmutable $dt;
    #[PzEnumAttribute] public BStatus $st; public string $x1; public string $x2; public string $x3; public string $x4;
    #[PzDtoAttribute] public BAddress $address;
    #[PzDtoAttribute(BItem::class)] public array $items;
}

$row = [];
for ($i = 1; $i <= 5; $i++) { $row["f$i"] = (string) $i; $row["s$i"] = "texto $i"; $row["d$i"] = "$i.5"; }
$row += ['n1' => 1, 'n2' => null, 'n3' => 'a', 'n4' => null, 'n5' => 2.5, 'b1' => '1', 'b2' => false,
    'a1' => [1, 2], 'm1' => 'x', 'dt' => '2026-10-22 10:00:00', 'st' => 2, 'x1' => 'a', 'x2' => 'b', 'x3' => 'c', 'x4' => 'd',
    'address' => ['street' => 'Rua A', 'city' => 'SP', 'zip' => '01001-000'],
    'items' => [['sku' => 'A', 'qty' => '1', 'price' => '1.5'], ['sku' => 'B', 'qty' => 2, 'price' => 2], ['sku' => 'C', 'qty' => 3, 'price' => 3]]];
$rows = array_fill(0, 10000, $row);

BRow::from($row); // aquece cache de reflexão
gc_collect_cycles();
$best = INF;
for ($run = 0; $run < 5; $run++) {
    $memBefore = memory_get_usage();
    $t = hrtime(true);
    $list = array_map(fn (array $r) => BRow::from($r), $rows);
    $elapsed = (hrtime(true) - $t) / 1e6;
    $mem = (memory_get_usage() - $memBefore) / 1048576;
    $best = min($best, $elapsed);
    unset($list);
}
$t = hrtime(true);
$list = array_map(fn (array $r) => BRow::from($r), $rows);
$mem = (memory_get_usage() - $memBefore) / 1048576;
$extra = '';
if (method_exists($list[0], 'isDirty')) {
    $t2 = hrtime(true);
    foreach ($list as $dto) { $dto->trackChanges(); }
    $extra = sprintf(" | trackChanges() em todos: %.1f ms", (hrtime(true) - $t2) / 1e6);
    $mem2 = (memory_get_usage() - $memBefore) / 1048576;
    $t2 = hrtime(true);
    foreach ($list as $dto) { $dto->isDirty(); }
    $extra .= sprintf(" | isDirty() em todos: %.1f ms | memória com tracking: %.1f MB", (hrtime(true) - $t2) / 1e6, $mem2);
}
printf("from() x10k: %.1f ms (melhor de 5) | memória da lista: %.1f MB%s\n", $best, $mem, $extra);
