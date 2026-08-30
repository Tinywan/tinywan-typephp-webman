--TEST--
Symfony AssetMapper pattern: array_filter with ARRAY_FILTER_USE_KEY
--FILE--
<?php

function strip_numeric_keys(array $parsed): array
{
    return array_filter($parsed, static fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
}

function main(): void
{
    var_dump(strip_numeric_keys([
        0 => '@symfony/stimulus-bundle',
        'package' => '@symfony/stimulus-bundle',
        1 => 'extra',
        'version' => '1.2.3',
        'alias' => 'stimulus',
    ]));
}
?>
--EXPECT--
array(3) {
  ["package"]=>
  string(24) "@symfony/stimulus-bundle"
  ["version"]=>
  string(5) "1.2.3"
  ["alias"]=>
  string(8) "stimulus"
}
