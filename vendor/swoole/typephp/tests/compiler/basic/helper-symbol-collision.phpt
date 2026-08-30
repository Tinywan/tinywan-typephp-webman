--TEST--
PHPX helper names do not collide with user functions
--FILE--
<?php

function deindirect(mixed $value): string
{
    return 'user:' . $value;
}

function globals_array(): string
{
    return 'user-globals';
}

function get_called_ce(): string
{
    return 'user-called-ce';
}

function get_callable_scope(): string
{
    return 'user-callable-scope';
}

function std_create_object(): string
{
    return 'user-create-object';
}

function get_create_object_fn(): string
{
    return 'user-create-object-fn';
}

function accept_value(mixed $value): mixed
{
    return $value;
}

class HelperSymbolCollision
{
    public array $values = [2, 3];

    private function double(int $value): int
    {
        return $value * 2;
    }

    public function run(): array
    {
        return array_map([$this, 'double'], $this->values);
    }

    public function calledClass(): string
    {
        return static::class;
    }
}

function main(): void
{
    $values = ['key' => 'value'];
    echo accept_value($values['key']), PHP_EOL;
    var_dump(is_array($GLOBALS));

    $object = new HelperSymbolCollision();
    var_dump($object->run());
    echo $object->calledClass(), PHP_EOL;

    echo deindirect('ok'), PHP_EOL;
    echo globals_array(), PHP_EOL;
    echo get_called_ce(), PHP_EOL;
    echo get_callable_scope(), PHP_EOL;
    echo std_create_object(), PHP_EOL;
    echo get_create_object_fn(), PHP_EOL;
}

?>
--EXPECT--
value
bool(true)
array(2) {
  [0]=>
  int(4)
  [1]=>
  int(6)
}
HelperSymbolCollision
user:ok
user-globals
user-called-ce
user-callable-scope
user-create-object
user-create-object-fn
