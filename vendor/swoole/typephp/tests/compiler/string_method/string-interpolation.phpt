--TEST--
String interpolation with complex expressions
--FILE--
<?php

class Person {
    public string $name;
    public function __construct(string $name) {
        $this->name = $name;
    }
}

function main() {
    $count = 5;
    $arr = [1, 2, 3];

    // Simple variable interpolation
    $s1 = "count is $count";
    var_dump($s1);

    // Array access interpolation
    $s2 = "first: {$arr[0]}, last: {$arr[2]}";
    var_dump($s2);

    // Property access interpolation
    $person = new Person("Alice");
    $s3 = "Name: {$person->name}";
    var_dump($s3);

    echo "done\n";
}

?>
--EXPECT--
string(10) "count is 5"
string(17) "first: 1, last: 3"
string(11) "Name: Alice"
done
