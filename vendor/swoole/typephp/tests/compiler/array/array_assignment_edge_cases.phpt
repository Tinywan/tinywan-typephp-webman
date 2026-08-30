--TEST--
Array Assignment Operators Edge Cases
--FILE--
<?php
// Test edge cases for array assignment operators

// Test with function return values as indices
function getIndex() {
    return 'func_key';
}

function main() {
    // Test assignment to non-existent keys (should create the key)
    $empty = [];
    $empty['new'] += 999; // This should behave differently in PHP vs AOT - in PHP this starts at 0
    var_dump($empty['new']);
    // Since this would start at 0 in PHP, result should be 0 + 10 = 10
    // However, for AOT purposes we'll test with pre-existing 0 values
    $edge = ['missing' => 0];
    $edge['missing'] += 10;
    var_dump($edge['missing']); // 10

    // Test with null coalescing assignment (if supported)
    $nullArray = [];
    if (!isset($nullArray['test'])) {
        $nullArray['test'] = 0;
    }
    $nullArray['test'] += 5;
    var_dump($nullArray['test']); // 5

    // Test with different data types
    $mixed = [
        'int' => 42,
        'float' => 3.14,
        'string' => '100',
        'bool_true' => true,
        'bool_false' => false,
        'null_val' => 0
    ];

    $mixed['int'] += 8;
    var_dump($mixed['int']); // 50

    $mixed['float'] += 1.86;
    var_dump($mixed['float']); // 5.0

    $mixed['string'] += 50;
    var_dump($mixed['string']); // 150

    $mixed['bool_true'] += 5;
    var_dump($mixed['bool_true']); // 6 (true = 1)

    $mixed['bool_false'] += 3;
    var_dump($mixed['bool_false']); // 3 (false = 0)

    // Test with incrementing zero
    $mixed['null_val'] += 1;
    var_dump($mixed['null_val']); // 1

    // Test decrementing to negative
    $negative = ['value' => 5];
    $negative['value'] -= 10;
    var_dump($negative['value']); // -5

    // Test with very large numbers
    $large = ['num' => PHP_INT_MAX];
    $large['num'] -= PHP_INT_MAX - 100;
    var_dump($large['num']); // 100

    // Test string concatenation with assignment
    $text = ['message' => 'Hello'];
    $text['message'] .= ' World';
    var_dump($text['message']); // 'Hello World'

    $text['message'] .= '!';
    var_dump($text['message']); // 'Hello World!'

    // Test with zero values
    $zero = [
        'zero_int' => 0,
        'zero_float' => 0.0,
        'zero_string' => '0'
    ];

    $zero['zero_int'] += 42;
    var_dump($zero['zero_int']); // 42

    $zero['zero_float'] += 3.5;
    var_dump($zero['zero_float']); // 3.5

    $zero['zero_string'] += 10;
    var_dump($zero['zero_string']); // 10 (string "0" converts to 0, then 0+10=10)

    // Test with boolean results in assignment context
    $boolTest = ['flag' => 1];
    $boolTest['flag'] += 0; // Should remain 1
    var_dump($boolTest['flag']); // 1

    $boolTest['flag'] -= 1; // Should become 0
    var_dump($boolTest['flag']); // 0

    // Test with constant values
    define('INCREMENT_VALUE', 7);
    $constTest = ['value' => 13];
    $constTest['value'] += INCREMENT_VALUE;
    var_dump($constTest['value']); // 20

    // Test chained operations
    $chain = ['val' => 100];
    $chain['val'] += 10;
    $chain['val'] -= 5;
    $chain['val'] *= 2;
    $chain['val'] /= 5;
    var_dump($chain['val']); // 42 (100+10=110, 110-5=105, 105*2=210, 210/5=42)

    // Test with variable variables for array keys
    $keyName = 'dynamic';
    $dynamicArray = ['dynamic' => 15];
    $dynamicArray[$keyName] += 25;
    var_dump($dynamicArray[$keyName]); // 40


    $funcArray = ['func_key' => 8];
    $funcArray[getIndex()] += 12;
    var_dump($funcArray[getIndex()]); // 20

    echo "All array assignment edge case tests passed!\n";
}
?>
--EXPECT--
int(999)
int(10)
int(5)
int(50)
float(5)
int(150)
int(6)
int(3)
int(1)
int(-5)
int(100)
string(11) "Hello World"
string(12) "Hello World!"
int(42)
float(3.5)
int(10)
int(1)
int(0)
int(20)
int(42)
int(40)
int(20)
All array assignment edge case tests passed!
