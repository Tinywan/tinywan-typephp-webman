--TEST--
self class
--FILE--
<?php
class EvaluatedValue
{
   const TYPE = 'string';
    public function run(string $value, string $type = self::TYPE)
    {
        var_dump($value);
        var_dump($type);
    }
}

function main() {
    $obj = new EvaluatedValue;
    $obj->run('null');
}
?>
--EXPECT--
string(4) "null"
string(6) "string"