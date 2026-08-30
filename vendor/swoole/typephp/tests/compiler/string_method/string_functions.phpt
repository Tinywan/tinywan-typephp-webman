--TEST--
String Functions
--FILE--
<?php
// Test basic string functions
$str = "Hello, World!";

$len = strlen($str);
var_dump($len);

$upper = strtoupper($str);
var_dump($upper);

$lower = strtolower($str);
var_dump($lower);

// Test substr
$substr = substr($str, 0, 5);
var_dump($substr);

$substr2 = substr($str, 7);
var_dump($substr2);

// Test strpos
$pos = strpos($str, "World");
var_dump($pos);

// Test str_replace
$replaced = str_replace("World", "PHP", $str);
var_dump($replaced);

// Test trim functions
$spaced = "  Hello, World!  ";
$trimmed = trim($spaced);
var_dump($trimmed);

$ltrimmed = ltrim($spaced);
var_dump($ltrimmed);

$rtrimmed = rtrim($spaced);
var_dump($rtrimmed);

// Test string concatenation
$part1 = "Hello";
$part2 = "World";
$concatenated = $part1 . " " . $part2 . "!";
var_dump($concatenated);

// Test explode and implode
$csv = "apple,banana,orange";
$fruits = explode(",", $csv);
var_dump($fruits);

$joined = implode("-", $fruits);
var_dump($joined);

// Test comparison functions
$cmp1 = strcmp("abc", "abc");
var_dump($cmp1);

// Windows is -1, linux is -3
$cmp2 = strcmp("abc", "def");
var_dump($cmp2 < 0);

$cmp3 = strcasecmp("ABC", "abc");
var_dump($cmp3);

// Test string formatting
$formatted = sprintf("Value: %d, Text: %s", 42, "hello");
var_dump($formatted);

// Test chunk split
$chunked = str_split("abcdefghij", 3);
var_dump($chunked);

// Test strrev
$reversed = strrev("hello");
var_dump($reversed);

// Test word functions
$word_count = str_word_count("Hello world!");
var_dump($word_count);

// Test ucfirst and ucwords
$mixed_case = "hello world from PHP";
$first_upper = ucfirst($mixed_case);
var_dump($first_upper);

$title_case = ucwords($mixed_case);
var_dump($title_case);

// Test number conversion from string
$num_str = "123";
$num = (int)$num_str;
var_dump($num);

$float_str = "45.67";
$float = (float)$float_str;
var_dump($float);

// Test string padding
$padded = str_pad("Hello", 10, "0", STR_PAD_LEFT);
var_dump($padded);

// Test string repeat
$repeated = str_repeat("xy", 4);
var_dump($repeated);

// Test strip_tags
$html = "<p>Hello <b>World</b>!</p>";
$stripped = strip_tags($html);
var_dump($stripped);
?>
--EXPECT--
int(13)
string(13) "HELLO, WORLD!"
string(13) "hello, world!"
string(5) "Hello"
string(6) "World!"
int(7)
string(11) "Hello, PHP!"
string(13) "Hello, World!"
string(15) "Hello, World!  "
string(15) "  Hello, World!"
string(12) "Hello World!"
array(3) {
  [0]=>
  string(5) "apple"
  [1]=>
  string(6) "banana"
  [2]=>
  string(6) "orange"
}
string(19) "apple-banana-orange"
int(0)
bool(true)
int(0)
string(22) "Value: 42, Text: hello"
array(4) {
  [0]=>
  string(3) "abc"
  [1]=>
  string(3) "def"
  [2]=>
  string(3) "ghi"
  [3]=>
  string(1) "j"
}
string(5) "olleh"
int(2)
string(20) "Hello world from PHP"
string(20) "Hello World From PHP"
int(123)
float(45.67)
string(10) "00000Hello"
string(8) "xyxyxyxy"
string(12) "Hello World!"
