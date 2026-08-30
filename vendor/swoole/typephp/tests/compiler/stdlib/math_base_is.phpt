--TEST--
Math functions: base conversion and is_* checks
--FILE--
<?php
// decbin / decoct / dechex
var_dump(decbin(0));
var_dump(decbin(10));
var_dump(decoct(0));
var_dump(decoct(10));
var_dump(dechex(0));
var_dump(dechex(255));

// bindec / octdec / hexdec
var_dump(bindec("1010"));
var_dump(bindec("0"));
var_dump(octdec("12"));
var_dump(octdec("0"));
var_dump(hexdec("FF"));
var_dump(hexdec("0"));

// is_finite / is_infinite / is_nan
var_dump(is_finite(0));
var_dump(is_finite(log(0)));
var_dump(is_infinite(0));
var_dump(is_infinite(log(0)));
var_dump(is_nan(0));
var_dump(is_nan(sqrt(-1)));
?>
--EXPECT--
string(1) "0"
string(4) "1010"
string(1) "0"
string(2) "12"
string(1) "0"
string(2) "ff"
int(10)
int(0)
int(10)
int(0)
int(255)
int(0)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
