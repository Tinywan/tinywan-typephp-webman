--TEST--
various property default values (array, int, float, string, bool, null, const)
--FILE--
<?php

class Test
{
    public $untypedArray = [];
    public mixed $mixedArray = [];
    public array $typedArray = [];
    public $untypedInt = 123;
    public int $typedInt = 123;
    public float $typedFloat = 1.5;
    public string $typedString = 'hello';
    public bool $typedBool = true;
    public $untypedNull = null;
    public $untypedConst = PHP_INT_MAX;

    public function show(): void
    {
        var_dump(
            $this->untypedArray,
            $this->mixedArray,
            $this->typedArray,
            $this->untypedInt,
            $this->typedInt,
            $this->typedFloat,
            $this->typedString,
            $this->typedBool,
            $this->untypedNull,
            $this->untypedConst
        );
    }
}

function main()
{
    $t = new Test();
    $t->show();
}
?>
--EXPECT--
array(0) {
}
array(0) {
}
array(0) {
}
int(123)
int(123)
float(1.5)
string(5) "hello"
bool(true)
NULL
int(9223372036854775807)
