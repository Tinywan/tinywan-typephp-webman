--TEST--
Complex Array Operations with Assignment
--FILE--
<?php
// Test complex array operations with assignment operators
// Test with array returned from function
function getReference() {
    $arr = [0 => 100];
    return $arr;
}

function main() {
    // Test with string keys and various operations
    $data = [
        'numbers' => [1, 2, 3, 4, 5],
        'values' => [
            'a' => 10,
            'b' => 20,
            'c' => 30
        ],
        'strings' => [
            'greeting' => 'Hello',
            'name' => 'World'
        ]
    ];

    // Test incrementing array values in a loop
    foreach (['a', 'b', 'c'] as $key) {
        $data['values'][$key] += 5;
    }
    var_dump($data['values']['a']); // 15
    var_dump($data['values']['b']); // 25
    var_dump($data['values']['c']); // 35

    $ref = getReference();
    $ref[0] += 25;
    var_dump($ref[0]); // 125

    // Test with conditional array access
    $conditional = ['positive' => 10, 'negative' => -5];
    $key = 'positive';
    $conditional[$key] += 15;
    var_dump($conditional[$key]); // 25

    $key = 'negative';
    $conditional[$key] -= 3;
    var_dump($conditional[$key]); // -8

    // Actually test incrementing an element at the end
    $indexed = [10, 20, 30];
    $indexed[0] += 5;
    var_dump($indexed[0]); // 15

    // Test with computed keys
    $computed = [
        0 => 100,
        1 => 200,
        2 => 300
    ];

    $index = 1;
    $computed[$index] *= 2;
    var_dump($computed[1]); // 400

    // Test with array_merge results
    $base = ['x' => 5, 'y' => 10];
    $additions = ['x' => 3, 'z' => 7];

    // Add values from additions to base where keys match
    foreach ($additions as $key => $value) {
        if (isset($base[$key])) {
            $base[$key] += $value;
        } else {
            $base[$key] = $value;
        }
    }
    var_dump($base['x']); // 8
    var_dump($base['y']); // 10
    var_dump($base['z']); // 7

    // Test with array_slice results (as variable)
    $fullArray = [0 => 10, 1 => 20, 2 => 30, 3 => 40];
    $indexToModify = 2;
    $fullArray[$indexToModify] += 5;
    var_dump($fullArray[$indexToModify]); // 35

    // Test with array returned from expression
    $exprResult = ['count' => 0];
    $exprResult['count'] += 1;
    $exprResult['count'] *= 10;
    $exprResult['count'] += 1;
    var_dump($exprResult['count']); // 11

    // Test with multidimensional arrays
    $matrix = [
        [1, 2, 3],
        [4, 5, 6],
        [7, 8, 9]
    ];

    $matrix[1][2] += 10; // $matrix[1][2] is 6, becomes 16
    var_dump($matrix[1][2]); // 16

    $matrix[0][0] *= 2;
    var_dump($matrix[0][0]); // 2

    // Test with array_keys and array_values
    $assoc = ['first' => 100, 'second' => 200, 'third' => 300];
    $keys = array_keys($assoc);

    foreach ($keys as $key) {
        $assoc[$key] += 10;
    }
    var_dump($assoc['first']); // 110

    // Test with array references
    $original = ['value' => 50];
    $xref =& $original;
    $xref['value'] += 25;
    var_dump($original['value']); // 75

    echo "All complex array operation tests passed!\n";
}
?>
--EXPECT--
int(15)
int(25)
int(35)
int(125)
int(25)
int(-8)
int(15)
int(400)
int(8)
int(10)
int(7)
int(35)
int(11)
int(16)
int(2)
int(110)
int(75)
All complex array operation tests passed!
