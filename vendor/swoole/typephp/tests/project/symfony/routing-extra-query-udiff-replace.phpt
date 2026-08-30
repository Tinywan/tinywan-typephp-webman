--TEST--
Symfony Routing pattern: array_udiff_assoc with array_diff_key and array_replace
--FILE--
<?php

function extra_query(array $parameters, array $variables, array $defaults, array $queryParameters): array
{
    $extra = array_udiff_assoc(
        array_diff_key($parameters, $variables),
        $defaults,
        static fn ($a, $b): int => $a == $b ? 0 : 1
    );

    return array_replace($extra, $queryParameters);
}

function main(): void
{
    $parameters = ['slug' => 'post', 'page' => '1', 'sort' => 'new', 'debug' => false];
    $variables = ['slug' => true];
    $defaults = ['page' => 1, 'sort' => 'old', 'debug' => false];
    $query = ['sort' => 'top', 'filter' => 'all'];

    var_dump(extra_query($parameters, $variables, $defaults, $query));
}
?>
--EXPECT--
array(2) {
  ["sort"]=>
  string(3) "top"
  ["filter"]=>
  string(3) "all"
}
