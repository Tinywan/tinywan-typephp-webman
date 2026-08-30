--TEST--
SSA object prop: nullable and union properties stay Var with null default
--FILE--
<?php
use native_types;

class FlexibleDefaults {
    public ?int $nullable;
    public ?object $nullableObject;
    public int|string $union;

    public function run(): void {
        var_dump($this->nullable);
        var_dump($this->nullableObject);
        var_dump($this->union);

        $this->nullable = 13;
        $this->nullableObject = null;
        $this->union = "ok";
        var_dump($this->nullable);
        var_dump($this->nullableObject);
        var_dump($this->union);

        $this->nullable = null;
        $this->nullableObject = null;
        $this->union = '';
        var_dump($this->nullable);
        var_dump($this->nullableObject);
        var_dump($this->union);
    }
}

function main(): void {
    (new FlexibleDefaults())->run();
}
?>
--EXPECT--
NULL
NULL
NULL
int(13)
NULL
string(2) "ok"
NULL
NULL
string(0) ""
