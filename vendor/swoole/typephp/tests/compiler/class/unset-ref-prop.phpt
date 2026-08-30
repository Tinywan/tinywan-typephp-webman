--TEST--
unset typed property preserves existing property reference
--FILE--
<?php

class UnsetRefProp {
    public int $value = 42;
    public string $name = "abc";
    public array $items = [1, 2];
}

function main() {
    eval('function unset_value(UnsetRefProp $obj) { unset($obj->value); }');
    eval('function unset_name(UnsetRefProp $obj) { unset($obj->name); }');
    eval('function unset_items(UnsetRefProp $obj) { unset($obj->items); }');

    $obj = new UnsetRefProp();

    $valueRef =& $obj->value;
    $valueRef = 7;
    unset_value($obj);
    var_dump($obj->value);
    var_dump($valueRef);
    $valueRef = 9;
    var_dump($obj->value);

    $nameRef =& $obj->name;
    unset_name($obj);
    var_dump($obj->name);
    var_dump($nameRef);
    $nameRef = "changed";
    var_dump($obj->name);

    $itemsRef =& $obj->items;
    unset_items($obj);
    var_dump($obj->items);
    var_dump($itemsRef);
    $itemsRef[] = 3;
    var_dump($obj->items);
}
?>
--EXPECT--
int(0)
int(0)
int(9)
string(0) ""
string(0) ""
string(7) "changed"
array(0) {
}
array(0) {
}
array(1) {
  [0]=>
  int(3)
}
