--TEST--
ArrayDef enforces direct list and map writes for Zend and Native classes
--FILE--
<?php

class ZendArrayDefBox
{
    #[ArrayDef(Type::String)]
    public array $names = [];

    #[ArrayDef(Type::Int, Type::String)]
    public array $labels = [];

    #[ArrayDef(Type::String, Type::Int)]
    public static array $staticCounters = [];
}

class PromotedArrayDefBox
{
    public function __construct(
        #[ArrayDef(Type::Int)] public array $values = [],
    ) {
    }
}

#[Native]
class NativeArrayDefBox
{
    #[ArrayDef(Type::Int)]
    public array $values = [];

    #[ArrayDef(Type::String, Type::Int)]
    public array $counters = [];
}

function writeDynamicList(ZendArrayDefBox $box, any $key, any $value): void
{
    $box->names[$key] = $value;
}

function writeDynamicMap(NativeArrayDefBox $box, any $key, any $value): void
{
    $box->counters[$key] = $value;
}

function main(): void
{
    $zend = new ZendArrayDefBox();
    $zend->names[] = 'first';
    $zend->names[count($zend->names)] = 'second';
    $zend->names[0] = 'changed';
    $zend->labels[10] = 'ten';
    ZendArrayDefBox::$staticCounters['writes'] = 1;

    $promoted = new PromotedArrayDefBox();
    $promoted->values[] = 13;

    $native = new NativeArrayDefBox();
    $native->values[] = 7;
    $native->values[count($native->values)] = 8;
    $native->values[1] = 9;
    $native->counters['ok'] = 11;

    writeDynamicList($zend, 1, 'dynamic');
    writeDynamicList($zend, count($zend->names), 'appended');
    writeDynamicMap($native, 'dynamic', 12);

    var_dump($zend->names, $zend->labels, ZendArrayDefBox::$staticCounters, $promoted->values, $native->values, $native->counters);

    try {
        writeDynamicList($zend, '1', 'bad-key');
    } catch (TypeError $error) {
        echo "list key type checked\n";
    }
    try {
        writeDynamicList($zend, 0, 123);
    } catch (TypeError $error) {
        echo "list value type checked\n";
    }
    try {
        writeDynamicMap($native, 1, 12);
    } catch (TypeError $error) {
        echo "map key type checked\n";
    }
    try {
        writeDynamicMap($native, 'bad', '12');
    } catch (TypeError $error) {
        echo "map value type checked\n";
    }
    try {
        writeDynamicList($zend, count($zend->names) + 1, 'out');
    } catch (Error $error) {
        echo "list bounds checked\n";
    }
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(7) "changed"
  [1]=>
  string(7) "dynamic"
  [2]=>
  string(8) "appended"
}
array(1) {
  [10]=>
  string(3) "ten"
}
array(1) {
  ["writes"]=>
  int(1)
}
array(1) {
  [0]=>
  int(13)
}
array(2) {
  [0]=>
  int(7)
  [1]=>
  int(9)
}
array(2) {
  ["ok"]=>
  int(11)
  ["dynamic"]=>
  int(12)
}
list key type checked
list value type checked
map key type checked
map value type checked
list bounds checked
