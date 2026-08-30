--TEST--
Fully qualified names vs relative names in namespace
--FILE--
<?php
namespace Foo\Bar {
    class Helper {
        public static function version(): string {
            return "1.0.0";
        }
    }

    function baz(): string {
        return "baz-in-foo-bar";
    }
}

namespace Foo {
    class Runner {
        // Relative: Bar\Helper resolves to Foo\Bar\Helper
        public static function run(): string {
            return Bar\Helper::version();
        }
    }
}

namespace {
    function main() {
        // Fully qualified calls
        var_dump(\Foo\Bar\Helper::version());
        var_dump(\Foo\Bar\baz());
        // Call into Foo namespace class
        var_dump(\Foo\Runner::run());
        echo "done\n";
    }
}
?>
--EXPECT--
string(5) "1.0.0"
string(14) "baz-in-foo-bar"
string(5) "1.0.0"
done
