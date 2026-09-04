--TEST--
strval / intval / floatval / boolval type conversions
--FILE--
<?php
function main() {
    // strval
    var_dump(strval(42));
    var_dump(strval(1.5));
    var_dump(strval(true));
    var_dump(strval(false));
    var_dump(strval(null));

    // intval
    var_dump(intval(42));
    var_dump(intval(3.14));
    var_dump(intval("42"));
    var_dump(intval("3.14"));
    var_dump(intval(true));
    var_dump(intval(false));

    // intval with an explicit base
    var_dump(intval("ff", 16));
    var_dump(intval("0x1A", 16));
    var_dump(intval("101", 2));
    var_dump(intval("777", 8));
    $base = 16;
    var_dump(intval("ff", $base));

    // An unpacked argument carries its own arity, which only the runtime
    // knows, so these must not be lowered as single-argument casts.
    $withBase = ["ff", 16];
    $single = ["42"];
    var_dump(intval(...$withBase));
    var_dump(intval(...$single));
    var_dump(strval(...$single));
    var_dump(floatval(...$single));
    var_dump(boolval(...$single));
    var_dump(intval("ff", ...[16]));

    // A named argument is likewise a single Arg node that does not have to
    // be the value being converted.
    var_dump(intval(value: "42"));

    // floatval
    var_dump(floatval(42));
    var_dump(floatval("3.14"));
    var_dump(floatval("42"));

    // boolval
    var_dump(boolval(1));
    var_dump(boolval(0));
    var_dump(boolval(""));
    var_dump(boolval("hello"));
    var_dump(boolval([]));
    var_dump(boolval([1]));
}
?>
--EXPECT--
string(2) "42"
string(3) "1.5"
string(1) "1"
string(0) ""
string(0) ""
int(42)
int(3)
int(42)
int(3)
int(1)
int(0)
int(255)
int(26)
int(5)
int(511)
int(255)
int(255)
int(42)
string(2) "42"
float(42)
bool(true)
int(255)
int(42)
float(42)
float(3.14)
float(42)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
