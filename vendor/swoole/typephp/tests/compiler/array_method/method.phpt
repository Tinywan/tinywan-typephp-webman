--TEST--
array_method: all array methods test
--FILE--
<?php

function _odd($var)
{
    return $var & 1;
}

function _cube($n)
{
    return ($n * $n * $n);
}

function _sum($carry, $item)
{
    $carry += $item;
    return $carry;
}

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    $array = array("FirSt" => 1, "SecOnd" => 4);
    Assert::eq($array->changeKeyCase(CASE_UPPER), array_change_key_case($array, CASE_UPPER));

    $array = array('a', 'b', 'c', 'd', 'e');
    Assert::eq($array->chunk(2), array_chunk($array, 2));

    $records = [
        [
            'id' => 2135,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ],
        [
            'id' => 3245,
            'first_name' => 'Sally',
            'last_name' => 'Smith',
        ],
        [
            'id' => 5342,
            'first_name' => 'Jane',
            'last_name' => 'Jones',
        ],
        [
            'id' => 5623,
            'first_name' => 'Peter',
            'last_name' => 'Doe',
        ]
    ];
    Assert::eq($records->column('id'), array_column($records, 'id'));

    $array = array(1, "hello", 1, "world", "hello");
    Assert::eq($array->countValues(), array_count_values($array));

    $array1 = array("a" => "green", "red", "blue", "red");
    $array2 = array("b" => "green", "yellow", "red");
    Assert::eq($array1->diff($array2), array_diff($array1, $array2));

    $array1 = array("a" => "green", "b" => "brown", "c" => "blue", "red");
    $array2 = array("a" => "green", "yellow", "red");
    Assert::eq($array1->diffAssoc($array2), array_diff_assoc($array1, $array2));

    $array1 = array('blue' => 1, 'red' => 2, 'green' => 3, 'purple' => 4);
    $array2 = array('green' => 5, 'yellow' => 7, 'cyan' => 8);
    Assert::eq($array1->diffKey($array2), array_diff_key($array1, $array2));

    $array = [6, 7, 8, 9, 10, 11, 12];
    Assert::eq($array->filter('_odd'), array_filter($array, '_odd'));

    $input = array("oranges", "apples", "pears");
    Assert::eq($input->flip(), array_flip($input));

    $array1 = array("a" => "green", "red", "blue");
    $array2 = array("b" => "green", "yellow", "red");
    Assert::eq($array1->intersect($array2), array_intersect($array1, $array2));
    Assert::eq($array1->intersectAssoc($array2), array_intersect_assoc($array1, $array2));

    $array = ['apple', 2, 3];
    Assert::eq($array->isList(), array_is_list($array));

    $array = ['first' => 1, 'second' => 4];
    Assert::eq($array->keyExists('first'), array_key_exists('first', $array));

    $array = ['a' => 1, 'b' => 2, 'c' => 3];
    Assert::eq($array->keyFirst(), array_key_first($array));
    Assert::eq($array->keyLast(), array_key_last($array));
    Assert::eq($array->keys(), array_keys($array));
    Assert::eq($array->values(), array_values($array));

    $a = [1, 2, 3, 4, 5];
    Assert::eq($a->map('_cube'), array_map('_cube', $a));

    $array = array(12, 10, 9);
    Assert::eq($array->pad(5, 0), array_pad($array, 5, 0));

    $a = array(2, 4, 6, 8);
    Assert::eq($a->product(), array_product($a));

    $array = array("Neo", "Morpheus", "Trinity", "Cypher", "Tank");
    $randomKeys = $array->rand(2);
    Assert::eq(count($randomKeys), 2);
    Assert::true($randomKeys[0] !== $randomKeys[1]);
    Assert::true(array_key_exists($randomKeys[0], $array));
    Assert::true(array_key_exists($randomKeys[1], $array));

    $array = array(1, 2, 3, 4, 5);
    Assert::eq($array->reduce('_sum'), array_reduce($array, '_sum'));

    $base = array("orange", "banana", "apple", "raspberry");
    $replacements = array(0 => "pineapple", 4 => "cherry");
    $replacements2 = array(0 => "grape");
    Assert::eq($base->replace($replacements, $replacements2), array_replace($base, $replacements, $replacements2));

    $array = array("php", 4.0, array("green", "red"));
    $rev = $array->reverse();
    Assert::eq($rev->reverse(), array_reverse(array_reverse($array)));

    $array = array(0 => 'blue', 1 => 'red', 2 => 'green', 3 => 'red');
    Assert::eq($array->search('green'), array_search('green', $array));

    $array = array("a", "b", "c", "d", "e");
    Assert::eq($array->slice(-2, 1), array_slice($array, -2, 1));

    $a = array(2, 4, 6, 8);
    Assert::eq($a->sum(), array_sum($a));

    $array = ["a" => "green", "red", "b" => "green", "blue", "red"];
    Assert::eq($array->unique(), array_unique($array));
    Assert::eq($array->count(), count($array));

    $array = array("Mac", "NT", "Irix", "Linux");
    Assert::eq($array->contains("Mac"), in_array("Mac", $array));
    Assert::eq($array->join(","), implode(",", $array));

    $array = [];
    Assert::true($array->isEmpty());

    $fruits1 = array("lemon", "orange", "banana", "apple");
    $fruits2 = array("lemon", "orange", "banana", "apple");
    sort($fruits2);
    Assert::eq($fruits1->sort(), $fruits2);
    Assert::eq($fruits1, $fruits2);

    $stack = array("orange", "banana", "apple", "raspberry");
    Assert::eq($stack->pop(), 'raspberry');
    Assert::eq(array_pop($stack), 'apple');

    $array = array("red","green");
    $array->push("blue");
    Assert::eq($array, ["red","green", "blue"]);
    array_push($array, "yellow");
    Assert::eq($array, ["red","green", "blue", "yellow"]);

    $stack = array("orange", "banana", "apple", "raspberry");
    Assert::eq($stack->shift(), 'orange');
    Assert::eq(array_shift($stack), 'banana');

    $queue = ["orange", "banana"];
    $queue->unshift("orange");
    Assert::eq($queue, ["orange", "orange", "banana"]);
    array_unshift($queue, "orange");
    Assert::eq($queue, ["orange", "orange", "orange", "banana"]);

    $array1 = array("red", "green", "blue", "yellow");
    $array2 = array("red", "green", "blue", "yellow");
    Assert::eq($array1->splice(2), array_splice($array2, 2));
    Assert::eq($array1, $array2);

    $find = array("Hello","world");
    $replace = array("B");
    $arr = array("Hello","world","!");
    Assert::eq($arr->replaceStr($find, $replace), str_replace($find, $replace, $arr));
}
?>
--EXPECT--
