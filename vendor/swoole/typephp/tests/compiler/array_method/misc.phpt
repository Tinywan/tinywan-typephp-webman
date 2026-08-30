--TEST--
Universal method call on native typed variables
--FILE--
<?php
use native_types;

function main()
{
    // Int methods
    $a = 100;
    $a->add(50);
    var_dump($a);

    $a->sub(30);
    var_dump($a);

    $a->mul(2);
    var_dump($a);

    $a->div(4);
    var_dump($a);

    $b = $a->add(10);
    var_dump($b);
    var_dump($a);

    // Int conversion
    var_dump($a->toString());
    var_dump($a->toFloat());

    // String methods
    $s = "hello world";
    var_dump($s->length());
    var_dump($s->upper());
    var_dump($s->substr(0, 5));
    $words = $s->split(" ");
    var_dump($words->count());

    // Array methods
    $arr = [];
    $arr->push(100);
    $arr->push(200);
    $arr->push(300);
    var_dump($arr->count());
    var_dump($arr->get(0));
    var_dump($arr->get(1));
    var_dump($arr->keyExists(0));
    var_dump($arr->isEmpty());
    var_dump($arr->isList());

    // Array pop
    $last = $arr->pop();
    var_dump($last);
    var_dump($arr->count());

    // Array clean
    $arr->clean();
    var_dump($arr->isEmpty());

    // Float methods
    $f = 3.14;
    $f->add(1.0);
    var_dump($f);
    var_dump($f->toInt());

    echo "done\n";
}
?>
--EXPECT--
int(100)
int(100)
int(100)
int(100)
int(110)
int(100)
string(3) "100"
float(100)
int(11)
string(11) "HELLO WORLD"
string(5) "hello"
int(2)
int(3)
int(100)
int(200)
bool(true)
bool(false)
bool(true)
int(300)
int(2)
bool(true)
float(3.14)
int(3)
done
