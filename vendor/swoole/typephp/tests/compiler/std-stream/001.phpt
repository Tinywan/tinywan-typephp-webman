--TEST--
std stream: vector push_back and use
--FILE--
<?php

function main() {
    $v = std::vector(Type::Stream);
    $tmpfile = tempnam(sys_get_temp_dir(), 'aot');

    $fp = fopen($tmpfile, 'w+');
    $fp->write("hello");
    $v[] = $fp;

    $fp2 = fopen($tmpfile, 'r');
    $v[] = $fp2;

    var_dump(count($v));

    $r = $v[0];
    $r->seek(0);
    var_dump($r->read(5));

    $r2 = $v[1];
    var_dump($r2->read(5));

    $v[0]->close();
    $v[1]->close();
    unlink($tmpfile);
}
?>
--EXPECT--
int(2)
string(5) "hello"
string(5) "hello"
