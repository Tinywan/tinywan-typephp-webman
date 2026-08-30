--TEST--
Universal method call: Array type methods
--FILE--
<?php

function _even_filter($v) { return $v % 2 == 0; }

function main()
{
    // push / count / isEmpty
    $arr = [];
    var_dump($arr->isEmpty());
    $arr->push(10);
    $arr->push(20);
    $arr->push(30);
    var_dump($arr->count());
    var_dump($arr->isEmpty());

    // pop
    $arr = [];
    $arr->push(100);
    $arr->push(200);
    $arr->push(300);
    var_dump($arr->pop());
    var_dump($arr->count());
    var_dump($arr->pop());
    var_dump($arr->pop());

    // get / set / del / keyExists
    $arr = [];
    $arr->set("name", "John");
    $arr->set("age", 30);
    var_dump($arr->get("name"));
    var_dump($arr->get("age"));
    var_dump($arr->keyExists("age"));
    $arr->del("age");
    var_dump($arr->keyExists("age"));
    var_dump($arr->keyExists("name"));

    // clean
    $arr = [];
    $arr->push(1);
    $arr->push(2);
    $arr->push(3);
    $arr->clean();
    var_dump($arr->isEmpty());
    var_dump($arr->count());

    // keyExists / contains
    $arr = [];
    $arr->push("apple");
    $arr->push("banana");
    $arr->push("cherry");
    var_dump($arr->keyExists(0));
    var_dump($arr->keyExists(5));
    var_dump($arr->contains("banana"));
    var_dump($arr->contains("grape"));

    // search
    $arr = [];
    $arr->push("red");
    $arr->push("green");
    $arr->push("blue");
    $arr->push("green");
    var_dump($arr->search("green"));
    var_dump($arr->search("yellow"));

    // isList
    $arr = [];
    $arr->push("a");
    $arr->push("b");
    $arr->push("c");
    var_dump($arr->isList());
    $hash = [];
    $hash->set("k", "v");
    var_dump($hash->isList());

    // keys / values
    $arr = [];
    $arr->set("x", 1);
    $arr->set("y", 2);
    $arr->set("z", 3);
    $keys = $arr->keys();
    var_dump($keys->count());
    var_dump($keys->contains("x"));
    var_dump($keys->contains("y"));
    $vals = $arr->values();
    var_dump($vals->contains(1));
    var_dump($vals->contains(3));

    // keyFirst / keyLast
    var_dump($arr->keyFirst());
    var_dump($arr->keyLast());

    // join
    $arr = [];
    $arr->push("one");
    $arr->push("two");
    $arr->push("three");
    var_dump($arr->join(", "));

    // merge (mutating)
    $arr1 = [];
    $arr1->push("a");
    $arr1->push("b");
    $arr2 = [];
    $arr2->push("c");
    $arr2->push("d");
    $arr1->merge($arr2);
    var_dump($arr1->count());
    var_dump($arr1->get(2));

    // slice
    $arr = [];
    $arr->push("a");
    $arr->push("b");
    $arr->push("c");
    $arr->push("d");
    $arr->push("e");
    $sliced = $arr->slice(1, 3);
    var_dump($sliced->count());
    var_dump($sliced->get(0));
    $sliced2 = $arr->slice(-2);
    var_dump($sliced2->count());
    var_dump($sliced2->get(0));

    // sort (mutating)
    $arr = [];
    $arr->push(3);
    $arr->push(1);
    $arr->push(4);
    $arr->push(1);
    $arr->push(5);
    $arr->sort();
    var_dump($arr->get(0));
    var_dump($arr->get(1));
    var_dump($arr->get(4));

    // reverse
    $arr = [];
    $arr->push(1);
    $arr->push(2);
    $arr->push(3);
    $reversed = $arr->reverse();
    var_dump($reversed->get(0));
    var_dump($reversed->get(2));

    // unique
    $arr = [];
    $arr->push("a");
    $arr->push("b");
    $arr->push("a");
    $arr->push("c");
    $unique = $arr->unique();
    var_dump($unique->count());

    // flip
    $arr = [];
    $arr->set("a", 1);
    $arr->set("b", 2);
    $flipped = $arr->flip();
    var_dump($flipped->keyExists(1));

    // sum / product
    $arr = [];
    $arr->push(2);
    $arr->push(4);
    $arr->push(6);
    var_dump($arr->sum());
    var_dump($arr->product());

    // chunk
    $arr = [];
    $arr->push(1);
    $arr->push(2);
    $arr->push(3);
    $arr->push(4);
    $arr->push(5);
    $chunks = $arr->chunk(2);
    var_dump($chunks->count());

    // diff
    $a = [];
    $a->push("green");
    $a->push("red");
    $a->push("blue");
    $b = [];
    $b->push("green");
    $b->push("yellow");
    $diff = $a->diff($b);
    var_dump($diff->count());
    var_dump($diff->contains("red"));
    var_dump($diff->contains("blue"));

    // filter
    $arr = [];
    $arr->push(1);
    $arr->push(2);
    $arr->push(3);
    $arr->push(4);
    $evens = $arr->filter('_even_filter');
    var_dump($evens->count());
    var_dump($evens->get(1));

    // values
    $arr = [];
    $arr->set("x", 10);
    $arr->set("y", 20);
    $vals2 = $arr->values();
    var_dump($vals2->count());
    var_dump($vals2->get(0));
    var_dump($vals2->get(1));

    // replace
    $base = [];
    $base->push("orange");
    $base->push("banana");
    $base->push("apple");
    $repl = [];
    $repl->push("pineapple");
    $result = $base->replace($repl);
    var_dump($result->get(0));

    // jsonEncode
    $arr = [];
    $arr->set("name", "test");
    $arr->set("value", 123);
    var_dump($arr->jsonEncode());

    // toInt / toFloat / toBool / toString
    $arr = [];
    $arr->push(1);
    var_dump($arr->toInt());
    var_dump($arr->toFloat());
    var_dump($arr->toBool());

    echo "done\n";
}
?>
--EXPECT--
bool(true)
int(3)
bool(false)
int(300)
int(2)
int(200)
int(100)
string(4) "John"
int(30)
bool(true)
bool(false)
bool(true)
bool(true)
int(0)
bool(true)
bool(false)
bool(true)
bool(false)
int(1)
bool(false)
bool(true)
bool(false)
int(3)
bool(true)
bool(true)
bool(true)
bool(true)
string(1) "x"
string(1) "z"
string(15) "one, two, three"
int(2)
NULL
int(3)
string(1) "b"
int(2)
string(1) "d"
int(1)
int(1)
int(5)
int(3)
int(1)
int(3)
bool(true)
int(12)
int(48)
int(3)
int(2)
bool(true)
bool(true)
int(2)
int(2)
int(2)
int(10)
int(20)
string(9) "pineapple"
string(27) "{"name":"test","value":123}"
int(1)
float(1)
bool(true)
done