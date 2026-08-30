--TEST--
Symfony AssetMapper pattern: uasort with array spread spaceship comparison
--FILE--
<?php

function sort_path_rows(array $rows): array
{
    uasort($rows, static fn (array $a, array $b): int => [(bool) $a[1], ...$a] <=> [(bool) $b[1], ...$b]);

    return $rows;
}

function main(): void
{
    $rows = [
        ['assets/z.css', ''],
        ['assets/app.js', 'App\\'],
        ['assets/a.css', ''],
        ['vendor/package.js', 'Vendor\\'],
    ];

    foreach (sort_path_rows($rows) as $row) {
        echo $row[0], '|', $row[1], "\n";
    }
}
?>
--EXPECT--
assets/a.css|
assets/z.css|
assets/app.js|App\
vendor/package.js|Vendor\
