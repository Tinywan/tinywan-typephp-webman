--TEST--
use function alias resolves imported function name
--FILE--
<?php
namespace FunctionAlias\Lib {
    function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}

namespace FunctionAlias\App {
    use function FunctionAlias\Lib\normalize as clean_name;

    function run_alias(): void
    {
        var_dump(clean_name('  AOT  '));
    }
}

namespace {
    function main(): void
    {
        FunctionAlias\App\run_alias();
    }
}
?>
--EXPECT--
string(3) "aot"
