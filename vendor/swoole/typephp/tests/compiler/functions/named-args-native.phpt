--TEST--
Native function call named arguments are reordered and validated
--FILE--
<?php
function makeUser(string $name, int $age, string $city = "Beijing", bool $vip = false): array
{
    return [
        "name" => $name,
        "age" => $age,
        "city" => $city,
        "vip" => $vip,
    ];
}

function traced(string $label, mixed $value): mixed
{
    echo $label, "\n";
    return $value;
}

function collect(string $first = "root", ...$items): array
{
    return [$first, $items];
}

function main(): void
{
    var_dump(makeUser(age: 20, name: "Tom", vip: true));
    var_dump(makeUser("Jane", city: "Shanghai", age: 18));
    var_dump(makeUser(
        age: traced('age', 20),
        name: traced('name', 'Tom'),
        vip: traced('vip', true),
    ));
    var_dump(collect(
        extra: traced('extra', 5),
        first: traced('first', 'B'),
    ));
}
?>
--EXPECT--
array(4) {
  ["name"]=>
  string(3) "Tom"
  ["age"]=>
  int(20)
  ["city"]=>
  string(7) "Beijing"
  ["vip"]=>
  bool(true)
}
array(4) {
  ["name"]=>
  string(4) "Jane"
  ["age"]=>
  int(18)
  ["city"]=>
  string(8) "Shanghai"
  ["vip"]=>
  bool(false)
}
age
name
vip
array(4) {
  ["name"]=>
  string(3) "Tom"
  ["age"]=>
  int(20)
  ["city"]=>
  string(7) "Beijing"
  ["vip"]=>
  bool(true)
}
extra
first
array(2) {
  [0]=>
  string(1) "B"
  [1]=>
  array(1) {
    ["extra"]=>
    int(5)
  }
}
