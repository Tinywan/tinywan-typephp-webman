--TEST--
Symfony Routing pattern: extra parameters diff and recursive object caster
--SKIPIF--
<?php exit('skip closures do not support reference parameters'); ?>
--FILE--
<?php

class Slug
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

function normalizeExtra(array $parameters, array $variables, array $defaults, array $queryParameters): array
{
    $extra = array_udiff_assoc(array_diff_key($parameters, $variables), $defaults, static fn ($a, $b) => $a == $b ? 0 : 1);
    $extra = array_replace($extra, $queryParameters);

    array_walk_recursive($extra, $caster = static function (&$value) use (&$caster): void {
        if (is_object($value)) {
            $value = (string) $value;
        } elseif (is_array($value)) {
            array_walk_recursive($value, $caster);
        }
    });

    return $extra;
}

function main(): void
{
    var_dump(normalizeExtra(
        ['id' => 10, 'page' => 1, 'slug' => new Slug('hello'), 'tags' => [new Slug('a'), new Slug('b')]],
        ['id' => true],
        ['page' => 1, 'slug' => 'old'],
        ['q' => new Slug('search')]
    ));
}
?>
--EXPECT--
array(3) {
  ["slug"]=>
  string(5) "hello"
  ["tags"]=>
  array(2) {
    [0]=>
    string(1) "a"
    [1]=>
    string(1) "b"
  }
  ["q"]=>
  string(6) "search"
}
