--TEST--
stream extension method support
--FILE--
<?php

#[MethodsFor(Type::Stream)]
final class StreamExtensions
{
    public static function readChunk(stream $stream, int $size): string
    {
        return fread($stream, $size);
    }

    public static function countLines(stream $stream): int
    {
        $count = 0;
        $pos = ftell($stream);
        rewind($stream);
        while (!feof($stream)) {
            $line = fgets($stream);
            if ($line !== false) {
                $count++;
            }
        }
        fseek($stream, $pos);
        return $count;
    }
}

function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    $tmpfile = tempnam(sys_get_temp_dir(), 'aot');

    // Test 1: stream_read_chunk extension → readChunk()
    $fp = fopen($tmpfile, 'w+');
    $fp->write("hello world");
    $fp->seek(0);
    Assert::eq($fp->readChunk(5), "hello");
    $fp->close();

    // Test 2: stream_count_lines extension → countLines()
    $fp2 = fopen($tmpfile, 'w+');
    $fp2->write("line1\nline2\nline3");
    $fp2->seek(0);
    Assert::eq($fp2->countLines(), 3);
    $fp2->close();

    unlink($tmpfile);
    echo "OK\n";
}
?>
--EXPECT--
OK
