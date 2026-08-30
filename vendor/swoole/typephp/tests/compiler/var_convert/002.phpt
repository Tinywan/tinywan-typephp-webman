--TEST--
var convert chained on array element
--SKIPIF--
<?php
if (PHP_OS_FAMILY != 'Linux') {
    echo "skip: only run on linux\n";
    exit(0);
}
--FILE--
<?php
#[MethodsFor(Type::Stream)]
final class StreamExtensions
{
    public static function writeTest(stream $stream): void
    {
        $stream->write('world');
    }
}

function main()
{
    $pair = stream_socket_pair(AF_UNIX, STREAM_SOCK_STREAM, 0);
    $pair[0]->toStream()->write('hello');
    var_dump($pair[1]->toStream()->read(5));

    $pair[0]->toStream()->writeTest();
    var_dump($pair[1]->toStream()->read(5));
}
?>
--EXPECT--
string(5) "hello"
string(5) "world"
