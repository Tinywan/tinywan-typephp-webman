--TEST--
Universal method call on stream resources with null guard
--FILE--
<?php

function main()
{
    // Test 1: fopen()->write() returns bytes written (php_fn: fwrite)
    $tmpfile = tempnam(sys_get_temp_dir(), 'aot');
    var_dump(fopen($tmpfile, 'w')->write("hello") !== false);

    // Test 2: fopen()->read() returns content (php_fn: fread)
    // (re-open for reading — new stream)
    var_dump(fopen($tmpfile, 'r')->read(5));

    // Test 3: fopen()->close() returns bool (php_fn: fclose)
    var_dump(fopen($tmpfile, 'r')->close());

    // Test 4: fopen()->eof() returns bool (php_fn: feof)
    $f = fopen($tmpfile, 'w');
    $f->write("test");
    $f->close();
    var_dump(fopen($tmpfile, 'r')->eof());

    // Test 5: fopen()->tell() returns position (php_fn: ftell)
    $r = fopen($tmpfile, 'r');
    var_dump($r->tell() === 0);
    $r->read(2);
    var_dump($r->tell() === 2);
    $r->close();

    // Test 6: fopen()->getChar() returns single char (php_fn: fgetc)
    $f2 = fopen($tmpfile, 'r');
    var_dump($f2->getChar());
    $f2->close();

    // Test 7: Null guard — fopen() with invalid mode causes null,
    // calling write() should throw Error
    try {
        @fopen($tmpfile, 'INVALID_MODE')->write('data');
        var_dump(false); // should not reach here
    } catch (\Error $e) {
        var_dump(str_contains($e->getMessage(), 'Invalid stream resource'));
    }

    unlink($tmpfile);
    echo "OK\n";
}
?>
--EXPECT--
bool(true)
string(5) "hello"
bool(true)
bool(false)
bool(true)
bool(true)
string(1) "t"
bool(true)
OK

