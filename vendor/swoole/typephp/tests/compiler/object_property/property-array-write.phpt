--TEST--
object property array append and keyed write use generic property path
--FILE--
<?php

class PropertyArrayWriteBox
{
    public array $items = [];

    public function append(mixed $value): void
    {
        $this->items[] = $value;
    }

    public function put(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }
}

function main() {
    $box = new PropertyArrayWriteBox();
    $box->append('first');
    $box->put('lang', 'php');
    var_dump($box->items);
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(5) "first"
  ["lang"]=>
  string(3) "php"
}
