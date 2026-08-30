--TEST--
strlen / strcmp / strcasecmp / strncmp / strncasecmp
--FILE--
<?php
// strlen
var_dump(strlen(""));
var_dump(strlen("hello"));
var_dump(strlen("Hello World!"));

// strcmp
var_dump(strcmp("abc", "abc"));
var_dump(strcmp("abc", "abd"));
var_dump(strcmp("abd", "abc"));

// strcasecmp
var_dump(strcasecmp("ABC", "abc"));
var_dump(strcasecmp("ABC", "abd"));
var_dump(strcasecmp("ABD", "abc"));

// strncmp
var_dump(strncmp("abc123", "abc456", 3));
var_dump(strncmp("abc123", "abd123", 3));
var_dump(strncmp("abd123", "abc123", 3));

// strncasecmp
var_dump(strncasecmp("ABC123", "abc456", 3));
var_dump(strncasecmp("ABC123", "ABD123", 3));
var_dump(strncasecmp("ABD123", "ABC123", 3));
?>
--EXPECT--
int(0)
int(5)
int(12)
int(0)
int(-1)
int(1)
int(0)
int(-1)
int(1)
int(0)
int(-1)
int(1)
int(0)
int(-1)
int(1)
