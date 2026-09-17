--TEST--
__FUNCTION__ uses the short name in namespaced methods
--FILE--
<?php
namespace MagicFunctionMethod {
    class Example {
        public function instanceMethod(): void {
            echo __FUNCTION__, "\n", __METHOD__, "\n";
        }

        public static function staticMethod(): void {
            echo __FUNCTION__, "\n", __METHOD__, "\n";
        }
    }

    function namespacedFunction(): void {
        echo __FUNCTION__, "\n";
    }
}

namespace {
    function main(): void {
        (new \MagicFunctionMethod\Example())->instanceMethod();
        \MagicFunctionMethod\Example::staticMethod();
        \MagicFunctionMethod\namespacedFunction();
    }
}
?>
--EXPECT--
instanceMethod
MagicFunctionMethod\Example::instanceMethod
staticMethod
MagicFunctionMethod\Example::staticMethod
MagicFunctionMethod\namespacedFunction
