--TEST--
Typed properties retain Zend reference type sources while PHP array elements remain dynamic
--FILE--
<?php

final class TypedReferenceHolder
{
    public string $value = 'instance';
    public static string $staticValue = 'static';
}

function appendSuffix(string &$value): void
{
    $value .= ':changed';
}

function replaceWithArray(mixed &$value): void
{
    $value = ['invalid'];
}

function appendThenThrow(string &$value): void
{
    $value .= ':throw';
    throw new RuntimeException('stop');
}

function main(): void
{
    $holder = new TypedReferenceHolder();

    appendSuffix($holder->value);
    appendSuffix(TypedReferenceHolder::$staticValue);
    var_dump($holder->value, TypedReferenceHolder::$staticValue);

    try {
        replaceWithArray($holder->value);
        echo "missing instance TypeError\n";
    } catch (TypeError $error) {
        echo "instance TypeError\n";
    }

    try {
        replaceWithArray(TypedReferenceHolder::$staticValue);
        echo "missing static TypeError\n";
    } catch (TypeError $error) {
        echo "static TypeError\n";
    }

    $array = [];
    $array['dynamic'] = 'array';
    appendSuffix($array['dynamic']);
    replaceWithArray($array['dynamic']);
    var_dump($array['dynamic']);

    $array['typed'] =& $holder->value;
    appendSuffix($array['typed']);
    try {
        replaceWithArray($array['typed']);
        echo "missing propagated TypeError\n";
    } catch (TypeError $error) {
        echo "propagated TypeError\n";
    }

    var_dump($holder->value, $array['typed']);

    $throwHolder = new TypedReferenceHolder();
    try {
        appendThenThrow($throwHolder->value);
    } catch (RuntimeException $error) {
        echo "property throw\n";
    }
    $throwArray = ['value' => 'array'];
    try {
        appendThenThrow($throwArray['value']);
    } catch (RuntimeException $error) {
        echo "array throw\n";
    }
    var_dump($throwHolder->value, $throwArray['value']);

    $callback = 'appendSuffix';
    $callback(std::ref($holder->value));
    $array['dynamic'] = 'dynamic';
    $callback(std::ref($array['dynamic']));
    var_dump($holder->value, $array['dynamic']);
}

?>
--EXPECT--
string(16) "instance:changed"
string(14) "static:changed"
instance TypeError
static TypeError
array(1) {
  [0]=>
  string(7) "invalid"
}
propagated TypeError
string(24) "instance:changed:changed"
string(24) "instance:changed:changed"
property throw
array throw
string(14) "instance:throw"
string(11) "array:throw"
string(32) "instance:changed:changed:changed"
string(15) "dynamic:changed"
