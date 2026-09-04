--TEST--
??= preserves references returned as writable array containers
--FILE--
<?php

function keyName(string $label): string
{
    echo "key:$label\n";
    return 'value';
}

function rhs(string $label): int
{
    echo "rhs:$label\n";
    return 42;
}

function &referencedValues(): array
{
    global $values;
    return $values;
}

function valuesByValue(): array
{
    return [];
}

class ReferenceContainer
{
    private array $values = [];

    public function &values(): array
    {
        return $this->values;
    }

    public function all(): array
    {
        return $this->values;
    }
}

function main(): void
{
    global $values;

    // A known by-reference function must write through to the global array.
    $values = [];
    referencedValues()[keyName('function-unset')] ??= rhs('function-unset');
    var_dump($values);

    // The set branch still evaluates the key once and keeps the RHS lazy.
    $values = ['value' => 7];
    var_dump(referencedValues()[keyName('function-set')] ??= rhs('function-set'));
    var_dump($values);

    // A dynamic call is resolved only at runtime, so its reference identity
    // must survive the same container stabilization boundary.
    $callback = 'referencedValues';
    $values = [];
    $callback()[keyName('dynamic')] ??= rhs('dynamic');
    var_dump($values);

    // The conservative dynamic-call path must also accept an ordinary
    // by-value array result as a disposable write target.
    $callback = 'valuesByValue';
    var_dump($callback()[keyName('dynamic-value')] ??= rhs('dynamic-value'));

    // Method return references follow the same write-through rules.
    $container = new ReferenceContainer();
    $container->values()[keyName('method')] ??= rhs('method');
    var_dump($container->all());
}
?>
--EXPECT--
key:function-unset
rhs:function-unset
array(1) {
  ["value"]=>
  int(42)
}
key:function-set
int(7)
array(1) {
  ["value"]=>
  int(7)
}
key:dynamic
rhs:dynamic
array(1) {
  ["value"]=>
  int(42)
}
key:dynamic-value
rhs:dynamic-value
int(42)
key:method
rhs:method
array(1) {
  ["value"]=>
  int(42)
}
