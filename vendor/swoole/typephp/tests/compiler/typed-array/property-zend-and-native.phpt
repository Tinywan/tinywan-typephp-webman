--TEST--
TypedProperty enforces direct list and map writes for Zend and Native classes
--FILE--
<?php

class ZendTypedPropertyBox
{
    #[StdList(Type::String)]
    public array $names = [];

    #[StdDict(Type::Int, Type::String)]
    public array $labels = [];

    #[StdDict(Type::String, Type::Int)]
    public static array $staticCounters = [];
}

class PromotedTypedPropertyBox
{
    public function __construct(
        public array $values = [],
    ) {
    }
}

#[Native]
class NativeTypedPropertyBox
{
    #[StdList(Type::Int)]
    public array $values = [];

    #[StdDict(Type::String, Type::Int)]
    public array $counters = [];
}

function writeDynamicList(ZendTypedPropertyBox $box, any $key, string $value): void
{
    $box->names[$key] = $value;
}

function writeDynamicMap(NativeTypedPropertyBox $box, any $key, int $value): void
{
    $box->counters[$key] = $value;
}

function main(): void
{
    $zend = new ZendTypedPropertyBox();
    $zend->names[] = 'first';
    $zend->names[count($zend->names)] = 'second';
    $zend->names[0] = 'changed';
    $zend->labels[10] = 'ten';
    ZendTypedPropertyBox::$staticCounters['writes'] = 1;

    $promoted = new PromotedTypedPropertyBox();
    $promoted->values[] = 13;

    $native = new NativeTypedPropertyBox();
    $native->values[] = 7;
    $native->values[count($native->values)] = 8;
    $native->values[1] = 9;
    $native->counters['ok'] = 11;

    writeDynamicList($zend, 1, 'dynamic');
    writeDynamicList($zend, count($zend->names), 'appended');
    writeDynamicMap($native, 'dynamic', 12);

    var_dump($zend->names, $zend->labels, ZendTypedPropertyBox::$staticCounters, $promoted->values, $native->values, $native->counters);

    try {
        writeDynamicList($zend, '1', 'bad-key');
    } catch (TypeError $error) {
        echo "list key type checked\n";
    }
    try {
        writeDynamicMap($native, 1, 12);
    } catch (TypeError $error) {
        echo "map key type checked\n";
    }
    writeDynamicList($zend, count($zend->names) + 1, 'out');
    var_dump($zend->names[4]);
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
map key type checked
string(3) "out"
