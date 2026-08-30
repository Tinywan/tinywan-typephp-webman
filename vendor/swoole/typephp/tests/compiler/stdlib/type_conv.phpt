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
float(42)
float(3.14)
float(42)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
