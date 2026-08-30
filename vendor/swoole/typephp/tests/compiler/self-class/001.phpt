--TEST--
self class
--FILE--
<?php
class EvaluatedValue
{
    public $value;
    public function __construct(string $value)
    {
        $this->value = $value;
    }
    public static function null(): EvaluatedValue
    {
        return new self('null');
    }
}

function main() {
    $obj = EvaluatedValue::null();
    var_dump($obj->value);
}
?>
--EXPECT--
string(4) "null"