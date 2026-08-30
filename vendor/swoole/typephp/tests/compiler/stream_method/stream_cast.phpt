--TEST--
toStream() keyword method for stream type casting
--FILE--
<?php

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    // Test 1: toStream() on an already-inferred stream variable
    $tmpfile = tempnam(sys_get_temp_dir(), 'aot');
    $fp = fopen($tmpfile, 'w+');
    $fp->write("hello world");
    $fp->seek(0);
    $data = $fp->toStream()->read(5);
    var_dump($data);
    $fp->close();

    // Test 2: toStream() on array elements where type isn't known
    $pipes = [];
    $pipes[0] = fopen($tmpfile, 'w');
    $w = $pipes[0]->toStream();
    $w->write("test data from pipe");
    $w->close();

    $pipes[1] = fopen($tmpfile, 'r');
    $r = $pipes[1]->toStream();
    $data2 = $r->read(1024);
    var_dump($data2);
    $r->close();

    // Test 3: toStream() in expression context (method chaining)
    $fp2 = fopen($tmpfile, 'w');
    $fp2->toStream()->write("chain test");
    $fp2->toStream()->close();

    $fp3 = fopen($tmpfile, 'r');
    var_dump($fp3->toStream()->read(10));
    $fp3->toStream()->close();

    unlink($tmpfile);
    echo "OK\n";
}
?>
--EXPECT--
string(5) "hello"
string(19) "test data from pipe"
string(10) "chain test"
OK
