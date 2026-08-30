--TEST--
str_contains / str_starts_with / str_ends_with edge cases
--FILE--
<?php
// str_contains
var_dump(str_contains("", ""));
var_dump(str_contains("hello", ""));
var_dump(str_contains("hello", "ll"));
var_dump(str_contains("hello", "xy"));
var_dump(str_contains("", "a"));

// str_starts_with
var_dump(str_starts_with("", ""));
var_dump(str_starts_with("hello", ""));
var_dump(str_starts_with("hello", "he"));
var_dump(str_starts_with("hello", "lo"));
var_dump(str_starts_with("", "a"));

// str_ends_with
var_dump(str_ends_with("", ""));
var_dump(str_ends_with("hello", ""));
var_dump(str_ends_with("hello", "lo"));
var_dump(str_ends_with("hello", "he"));
var_dump(str_ends_with("", "a"));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
