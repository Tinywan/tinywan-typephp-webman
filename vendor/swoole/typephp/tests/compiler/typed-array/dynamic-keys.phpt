--TEST--
Typed PHP arrays strictly check dynamic keys without changing PHPX or coercing keys
--FILE--
<?php
function main(): void
{
    $list = std::list(Type::Int);
    $key = std::any(50);
    $list[$key] = 7;
    var_dump($list[$key], isset($list[$key]), empty($list[$key]));
    var_dump(array_key_exists($key, $list), $list->keyExists($key), $list->get($key));
    unset($list[$key]);
    var_dump(isset($list[$key]), count($list));

    $dict = std::dict(Type::Str, Type::Int);
    $name = std::any('123');
    $dict[$name] = 9;
    var_dump($dict[$name], key_exists(array: $dict, key: $name), $dict->get($name));
    foreach ($dict as $stringKey => $value) {
        var_dump(is_string($stringKey), $stringKey, $value);
    }

    try { $list[std::any('50')] = 1; } catch (TypeError $error) { echo "write rejected\n"; }
    try { var_dump($dict[std::any(123)]); } catch (TypeError $error) { echo "read rejected\n"; }
    try { var_dump(isset($list[std::any(true)])); } catch (TypeError $error) { echo "isset rejected\n"; }
    try { unset($dict[std::any(1.5)]); } catch (TypeError $error) { echo "unset rejected\n"; }
    try { var_dump(array_key_exists(std::any('50'), $list)); } catch (TypeError $error) { echo "key exists rejected\n"; }
    try { var_dump($dict->get(std::any(123))); } catch (TypeError $error) { echo "get rejected\n"; }
    var_dump(count($list), count($dict));
    unset($dict[$name]);
    var_dump(count($dict));
}
?>
--EXPECT--
int(7)
bool(true)
bool(false)
bool(true)
bool(true)
int(7)
bool(false)
int(0)
int(9)
bool(true)
int(9)
bool(true)
string(3) "123"
int(9)
write rejected
read rejected
isset rejected
unset rejected
key exists rejected
get rejected
int(0)
int(1)
int(0)
