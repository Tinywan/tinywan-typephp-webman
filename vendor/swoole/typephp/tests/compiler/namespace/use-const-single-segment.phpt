--TEST--
use const with single-segment global names, aliases, and runtime constants
--FILE--
<?php
namespace App {
    use const PHP_EOL;
    use const PHP_EOL as LINE_END;
    use const TYPEPHP_RUNTIME_SINGLE_SEGMENT as RUNTIME_VALUE;

    const ANSWER = 42;

    function hello(): string
    {
        return "hello" . PHP_EOL;
    }

    function helloAlias(): string
    {
        return "alias" . LINE_END;
    }

    function runtimeValue(): string
    {
        return RUNTIME_VALUE;
    }
}

namespace {
    use const App\ANSWER;

    function main(): void
    {
        echo \App\hello();
        echo \App\helloAlias();
        echo ANSWER, "\n";

        define('TYPEPHP_RUNTIME_SINGLE_SEGMENT', 'runtime-value');
        define('RUNTIME_VALUE', 'wrong-alias-fallback');
        echo \App\runtimeValue(), "\n";
    }
}
?>
--EXPECT--
hello
alias
42
runtime-value
