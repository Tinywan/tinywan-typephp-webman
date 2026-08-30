--TEST--
Array Assignment Operators
--FILE--
<?php
// Test array assignment operators
$array = ['a' => 5, 'b' => 10, 'c' => 15];

// Test += operator on array elements
var_dump($array['a']); // 5
$array['a'] += 10;
var_dump($array['a']); // 15

var_dump($array['b']); // 10
$array['b'] += $array['a']; // 10 + 15 = 25
var_dump($array['b']); // 25

// Test -= operator on array elements
$array['c'] -= 5;
var_dump($array['c']); // 10

$array['b'] -= $array['a']; // 25 - 15 = 10
var_dump($array['b']); // 10

// Test *= operator on array elements
$array['a'] *= 2;
var_dump($array['a']); // 30

$array['c'] *= $array['a']; // 10 * 30 = 300
var_dump($array['c']); // 300

// Test /= operator on array elements
$array['c'] /= 10;
var_dump($array['c']); // 30

$array['a'] /= $array['b']; // 30 / 10 = 3
var_dump($array['a']); // 3 (as float)

// Test %= operator on array elements
$modArray = ['x' => 25, 'y' => 7];
$modArray['x'] %= 7; // 25 % 7 = 4
var_dump($modArray['x']); // 4

$modArray['y'] %= 3; // 7 % 3 = 1
var_dump($modArray['y']); // 1

// Test .= operator on array elements (string concatenation)
$strArray = ['msg' => 'Hello', 'suffix' => ' World'];
$strArray['msg'] .= $strArray['suffix'];
var_dump($strArray['msg']); // 'Hello World'

$strArray['suffix'] .= '!';
var_dump($strArray['suffix']); // ' World!'

// Test with numeric indices
$numericArray = [0 => 100, 1 => 200, 2 => 300];
$numericArray[0] += 50;
var_dump($numericArray[0]); // 150

$numericArray[1] -= 50;
var_dump($numericArray[1]); // 150

$numericArray[2] *= 2;
var_dump($numericArray[2]); // 600

// Test with variable keys
$key = 'dynamic';
$dynamicArray = ['dynamic' => 42, 'static' => 10];
$dynamicArray[$key] += 8;
var_dump($dynamicArray[$key]); // 50

// Test with expression as key
$exprArray = [1 => 10, 2 => 20, 3 => 30];
$exprArray[1 + 1] += 5; // $exprArray[2] += 5
var_dump($exprArray[2]); // 25

// Test nested array assignment operators
$nested = [
    'level1' => [
        'level2' => 100
    ]
];
$nested['level1']['level2'] += 50;
var_dump('nested array assignment operators', $nested['level1']['level2']); // 150

// Test with function results
$funcArray = ['value' => 10];
$funcArray['value'] += strlen("hello"); // 10 + 5 = 15
var_dump($funcArray['value']); // 15

// Test multiple operations in sequence
$seqArray = ['counter' => 0];
$seqArray['counter'] += 5;
$seqArray['counter'] *= 2;
$seqArray['counter'] -= 3;
$seqArray['counter'] /= 7;
var_dump($seqArray['counter']); // 1 (7*2-3=7, then 7/7=1)

$exprResult = ['value' => 10];
$compoundResult = ($exprResult['value'] += 5);
var_dump($exprResult['value']);
var_dump($compoundResult);

echo "All array assignment tests passed!\n";
?>
--EXPECT--
int(5)
int(15)
int(10)
int(25)
int(10)
int(10)
int(30)
int(300)
int(30)
int(3)
int(4)
int(1)
string(11) "Hello World"
string(7) " World!"
int(150)
int(150)
int(600)
int(50)
int(25)
string(33) "nested array assignment operators"
int(150)
int(15)
int(1)
int(15)
int(15)
All array assignment tests passed!
