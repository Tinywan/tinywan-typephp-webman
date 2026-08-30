--TEST--
new and call
--FILE--
<?php
class FooTestNewAndCall {
    public function bar(string $name)
    {
        var_dump('hello ' . $name . '!');
    }
}

function main() {
   (new FooTestNewAndCall())->bar('world');
    echo "done\n";
}

?>
--EXPECT--
string(12) "hello world!"
done