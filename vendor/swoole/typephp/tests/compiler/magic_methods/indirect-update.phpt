--TEST--
Magic Methods - indirect property update uses __get by reference
--FILE--
<?php
declare(strict_types=1);

class MagicUpdateTest
{
    private array $values = [];

    public function __isset(string $name): bool
    {
        echo "__isset($name)\n";
        return false;
    }

    public function &__get(string $name): mixed
    {
        echo "__get($name)\n";
        return $this->values[$name];
    }

    public function value(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }
}

function main(): void
{
    $probe = new MagicUpdateTest();
    $probe->value[] = 42;
    var_dump($probe->value('value'));
}
?>
--EXPECT--
__get(value)
array(1) {
  [0]=>
  int(42)
}
