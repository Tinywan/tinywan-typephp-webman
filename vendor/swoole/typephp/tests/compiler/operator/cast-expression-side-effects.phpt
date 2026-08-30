--TEST--
cast operands are evaluated once
--FILE--
<?php

function make_value(string $tag, mixed $value): mixed
{
    echo "make:$tag\n";
    return $value;
}

function main(): void
{
    var_dump((int) make_value('int', '42'));
    var_dump((float) make_value('float', '2.5'));
    var_dump((string) make_value('string', 123));
    var_dump((bool) make_value('bool', []));

    $object = (object) make_value('object', ['name' => 'aot']);
    var_dump($object->name);

    $array = (array) make_value('array', $object);
    var_dump($array['name']);
}
?>
--EXPECT--
make:int
int(42)
make:float
float(2.5)
make:string
string(3) "123"
make:bool
bool(false)
make:object
string(3) "aot"
make:array
string(3) "aot"
