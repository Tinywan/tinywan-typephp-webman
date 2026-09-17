--TEST--
StdList and StdDict properties support statically typed PHP array/object/any values
--FILE--
<?php
class PhpValueProperties
{
    #[StdList(Type::Array)] public array $arrays = [];
    #[StdList(Type::Object)] public array $objects = [];
    #[StdDict(Type::Str, Type::Any)] public array $values = [];
}
function putArray(PhpValueProperties $s, array $value): void { $s->arrays[] = $value; }
function putObject(PhpValueProperties $s, object $value): void { $s->objects[] = $value; }
function main(): void
{
    $s = new PhpValueProperties();
    putArray($s, [1, 2]);
    putObject($s, new stdClass());
    $s->values['int'] = 3;
    $s->values['str'] = 'four';
    var_dump($s->arrays[0], $s->objects[0] instanceof stdClass, $s->values['int'], $s->values['str']);
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
bool(true)
int(3)
string(4) "four"
