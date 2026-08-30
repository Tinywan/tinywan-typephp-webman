--TEST--
static calls
--FILE--
<?php
namespace Foo {
    class Stream
    {
        public function pipe(self $stream): void
        {
            var_dump(get_class($stream));
        }
    }
}
namespace {
    function main() {
          $stream1 = new Foo\Stream();
          $stream2 = new Foo\Stream();
          $stream1->pipe($stream2);
    }
}
?>
--EXPECT--
string(10) "Foo\Stream"