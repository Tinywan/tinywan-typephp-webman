--TEST--
Group use imports for classes, functions, and constants
--FILE--
<?php
namespace Vendor\Package {
    class ClassA {
        public static function name(): string { return "ClassA"; }
    }
    class ClassB {
        public static function name(): string { return "ClassB"; }
    }
    class ClassC {
        public static function name(): string { return "ClassC"; }
    }
    function funcA(): string { return "funcA"; }
    function funcB(): string { return "funcB"; }
    const CONST_A = "constA";
    const CONST_B = "constB";
}

namespace {
    use Vendor\Package\{ClassA, ClassB, ClassC};
    use function Vendor\Package\{funcA, funcB};
    use const Vendor\Package\{CONST_A, CONST_B};

    function main() {
        var_dump(ClassA::name());
        var_dump(ClassB::name());
        var_dump(ClassC::name());
        var_dump(funcA());
        var_dump(funcB());
        var_dump(CONST_A);
        var_dump(CONST_B);
        echo "done\n";
    }
}
?>
--EXPECT--
string(6) "ClassA"
string(6) "ClassB"
string(6) "ClassC"
string(5) "funcA"
string(5) "funcB"
string(6) "constA"
string(6) "constB"
done
