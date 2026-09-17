--TEST--
unset nested array offsets mutates original, skips missing parents, and evaluates keys in order
--FILE--
<?php

class NestedUnsetHolder {
    public array $items = ['outer' => ['remove' => 1, 'keep' => 2]];
}

function unset_chain_key(string $key): string {
    echo "key:$key\n";
    return $key;
}

function main(): void {
    $items = ['outer' => ['deep' => ['remove' => 1, 'keep' => 2]]];
    $copy = $items;
    unset($items['outer']['deep']['remove']);
    var_dump($items['outer']['deep']);
    var_dump($copy['outer']['deep']['remove']);

    unset($items[unset_chain_key('missing')][unset_chain_key('leaf')]);
    var_dump(isset($items['missing']));

    $items['empty'] = null;
    unset($items['empty']['leaf']);
    var_dump($items['empty']);

    $items[''] = ['remove' => 9];
    unset($items[null]['remove']);
    var_dump($items['']);

    $holder = new NestedUnsetHolder();
    unset($holder->items['outer']['remove']);
    var_dump($holder->items['outer']);

    unset($items[unset_chain_key('outer')][unset_chain_key('deep')][unset_chain_key('keep')],
        $holder->items[unset_chain_key('outer')][unset_chain_key('keep')]);
    var_dump($items['outer']['deep'], $holder->items['outer']);
}
?>
--EXPECT--
array(1) {
  ["keep"]=>
  int(2)
}
int(1)
key:missing
key:leaf
bool(false)
NULL
array(0) {
}
array(1) {
  ["keep"]=>
  int(2)
}
key:outer
key:deep
key:keep
key:outer
key:keep
array(0) {
}
array(0) {
}
