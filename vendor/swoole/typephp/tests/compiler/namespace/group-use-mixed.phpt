--TEST--
Mixed group use imports for class, function, and constant
--FILE--
<?php
namespace MixedGroup\Lib {
    class Formatter {
        public static function wrap(string $value): string
        {
            return '[' . $value . ']';
        }
    }

    function label(string $value): string
    {
        return 'label:' . $value;
    }

    const DEFAULT_VALUE = 'mixed';
}

namespace {
    use MixedGroup\Lib\{Formatter, function label, const DEFAULT_VALUE};

    function main(): void
    {
        var_dump(Formatter::wrap(DEFAULT_VALUE));
        var_dump(label(DEFAULT_VALUE));
    }
}
?>
--EXPECT--
string(7) "[mixed]"
string(11) "label:mixed"
