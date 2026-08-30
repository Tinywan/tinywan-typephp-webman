--TEST--
Composite static checks preserve unknown runtime guards and float widening
--FILE--
<?php

function float_or_string(float|string $value): float|string
{
    return $value;
}

function variadic_float_or_string(float|string ...$values): array
{
    return $values;
}

class CompositeEdgeBox
{
    public float|string $number;
    public true|null $flag = null;

    public function setDynamicFlag(mixed $value): void
    {
        $this->flag = $value;
    }
}

function main(): void
{
    var_dump(float_or_string(1));
    var_dump(float_or_string(any(2)));
    var_dump(variadic_float_or_string(3, "ok"));
    $closure = fn (float|string $value): float|string => $value;
    var_dump($closure(5));

    $box = new CompositeEdgeBox();
    $box->number = 4;
    var_dump($box->number);
    $box->flag = true;
    var_dump($box->flag);

    try {
        $box->setDynamicFlag(false);
    } catch (TypeError $e) {
        var_dump(get_class($e));
    }
}
?>
--EXPECT--
float(1)
float(2)
array(2) {
  [0]=>
  float(3)
  [1]=>
  string(2) "ok"
}
float(5)
float(4)
bool(true)
string(9) "TypeError"
