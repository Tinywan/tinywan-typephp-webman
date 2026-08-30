--TEST--
Object cast (object) from array
--FILE--
<?php

function main() {
    $data = (object) ["name" => "test", "value" => 42];
    var_dump($data->name);
    var_dump($data->value);

    $empty = (object) [];
    var_dump((array) $empty);

    echo "done\n";
}

?>
--EXPECT--
string(4) "test"
int(42)
array(0) {
}
done
