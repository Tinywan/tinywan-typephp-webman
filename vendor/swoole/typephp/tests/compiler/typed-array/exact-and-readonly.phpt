--TEST--
Typed array explicit key conversions, readonly calls and dynamic by-value COW isolation
--FILE--
<?php
function dynamic_mutator(array &$array): void { $array[] = 'wrong'; }
function main(): void
{
    $list = std::list(Type::Int);
    $key = std::any(50);
    $list[$key->toInt()] = 7;
    $list[] = 8;
    var_dump($list[50], array_search(8, $list, true), count(array_keys($list)));
    $callback = 'dynamic_mutator';
    // Zend warns about the unknown reference signature; it receives a value
    // snapshot, never a reference to the statically typed array.
    @$callback($list);
    $alias =& $list;
    @$callback($alias);
    var_dump(count($list));
    $dict = std::dict(Type::Str, Type::Int);
    $stringKey = std::any('123');
    $dict[$stringKey->toString()] = 9;
    var_dump(array_keys($dict), $dict['123']);
    var_dump(array_key_exists('123', $dict), key_exists(array: $dict, key: '123'));
    var_dump($dict->keyExists('123'), $dict->get('123'), $dict->keyExists('missing'));
    $list[std::any('50')->toInt()] = 10;
    var_dump($list[50], count($list));
}
?>
--EXPECT--
int(7)
int(51)
int(2)
int(2)
array(1) {
  [0]=>
  int(123)
}
int(9)
bool(true)
bool(true)
bool(true)
int(9)
bool(false)
int(10)
int(2)
