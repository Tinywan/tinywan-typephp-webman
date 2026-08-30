--TEST--
Universal method call: String type methods
--FILE--
<?php

function main()
{
    // length / isEmpty
    $s = "hello world";
    var_dump($s->length());
    var_dump($s->isEmpty());
    $empty = "";
    var_dump($empty->isEmpty());

    // upper / lower
    $s = "Hello World";
    var_dump($s->upper());
    var_dump($s->lower());

    // lowerFirst / upperFirst
    $s = "Hello World";
    var_dump($s->lowerFirst());
    var_dump($s->upperFirst());

    // upperWords
    $s = "hello world!";
    var_dump($s->upperWords());

    // trim / lTrim / rTrim
    $s = "  hello world  ";
    var_dump($s->trim());
    var_dump($s->lTrim());
    var_dump($s->rTrim());

    // startsWith / endsWith / contains
    $s = "hello world";
    var_dump($s->startsWith("hello"));
    var_dump($s->startsWith("world"));
    var_dump($s->endsWith("world"));
    var_dump($s->endsWith("hello"));
    var_dump($s->contains("lo wo"));
    var_dump($s->contains("xxx"));

    // indexOf / lastIndexOf
    $s = "hello world";
    var_dump($s->indexOf("world"));
    var_dump($s->indexOf("xxx"));
    var_dump($s->lastIndexOf("o"));

    // iCompare / compare
    $s = "abc";
    var_dump($s->compare("abc"));
    var_dump($s->compare("abd"));
    var_dump($s->iCompare("ABC"));

    // reverse / md5 / sha1 / crc32
    $s = "hello";
    var_dump($s->reverse());
    var_dump($s->md5());
    var_dump($s->sha1());
    var_dump($s->crc32());

    // base64Encode / base64Decode
    $s = "hello";
    $encoded = $s->base64Encode();
    var_dump($encoded);
    var_dump($encoded->base64Decode());

    // urlEncode / urlDecode
    $s = "hello world";
    $encoded = $s->urlEncode();
    var_dump($encoded);
    var_dump($encoded->urlDecode());

    // equals
    $s = "hello";
    var_dump($s->equals("hello"));
    var_dump($s->equals("HELLO"));
    var_dump($s->equals("HELLO", true));

    // isNumeric
    $n1 = "123";
    $n2 = "12.3";
    $n3 = "abc";
    var_dump($n1->isNumeric());
    var_dump($n2->isNumeric());
    var_dump($n3->isNumeric());

    // substr
    $s = "hello world";
    var_dump($s->substr(6));
    var_dump($s->substr(0, 5));
    var_dump($s->substr(-5));

    // split
    $s = "a,b,c,d";
    $parts = $s->split(",");
    var_dump($parts->count());
    var_dump($parts->get(0));
    var_dump($parts->get(2));

    // stripTags
    $s = "<p>Hello <b>World</b></p>";
    var_dump($s->stripTags(""));

    // addSlashes
    $s = "O'Reilly";
    var_dump($s->addSlashes());

    // toInt / toFloat / toBool
    $s = "123";
    var_dump($s->toInt());
    $s = "3.14";
    var_dump($s->toFloat());
    $s = "1";
    var_dump($s->toBool());

    // substrCount
    $s = "hello hello world";
    var_dump($s->substrCount("hello"));

    // wordCount
    $s = "hello world test";
    var_dump($s->wordCount());

    echo "done\n";
}
?>
--EXPECT--
int(11)
bool(false)
bool(true)
string(11) "HELLO WORLD"
string(11) "hello world"
string(11) "hello World"
string(11) "Hello World"
string(12) "Hello World!"
string(11) "hello world"
string(13) "hello world  "
string(13) "  hello world"
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
int(6)
bool(false)
int(7)
int(0)
int(-1)
int(0)
string(5) "olleh"
string(32) "5d41402abc4b2a76b9719d911017c592"
string(40) "aaf4c61ddcc5e8a2dabede0f3b482cd9aea9434d"
int(907060870)
string(8) "aGVsbG8="
string(5) "hello"
string(11) "hello+world"
string(11) "hello world"
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
string(5) "world"
string(5) "hello"
string(5) "world"
int(4)
string(1) "a"
string(1) "c"
string(11) "Hello World"
string(9) "O\'Reilly"
int(123)
float(3.14)
bool(true)
int(2)
int(3)
done
