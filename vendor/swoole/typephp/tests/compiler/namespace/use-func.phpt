--TEST--
use function
--FILE--
<?php
namespace App\Foo {
    function bar() {
        var_dump(__FUNCTION__);
    }
}

namespace {
    use function App\Foo\bar;
    function main()
    {
        bar();
    }
}
?>
--EXPECT--
string(11) "App\Foo\bar"