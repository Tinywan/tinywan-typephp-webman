--TEST--
base64_encode / base64_decode edge cases
--FILE--
<?php
// Empty string
var_dump(base64_encode(""));
var_dump(base64_decode(""));

// Basic round-trip
var_dump(base64_encode("Hello World!"));
var_dump(base64_decode("SGVsbG8gV29ybGQh"));

// Binary data
$data = chr(0) . chr(1) . chr(255) . chr(127);
var_dump(base64_encode($data));
var_dump(base64_decode("AAH/fw==") === $data);

// Strict mode: valid input
var_dump(base64_decode("SGVsbG8gV29ybGQh", true));

// Strict mode: invalid padding
var_dump(base64_decode("====", true));

// Non-strict mode: garbage input
var_dump(base64_decode("!!!", false));
?>
--EXPECT--
string(0) ""
string(0) ""
string(16) "SGVsbG8gV29ybGQh"
string(12) "Hello World!"
string(8) "AAH/fw=="
bool(true)
string(12) "Hello World!"
bool(false)
string(0) ""
