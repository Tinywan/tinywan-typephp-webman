--TEST--
Mixed use class and use function imports
--FILE--
<?php
namespace Utils\Str {
    function slug(string $s): string {
        return str_replace(" ", "-", strtolower($s));
    }

    function prefix(string $s, string $p): string {
        return "{$p}:{$s}";
    }
}

namespace Utils\Math {
    function double(int $x): int {
        return $x * 2;
    }
}

namespace App {
    use function Utils\Str\slug;
    use function Utils\Str\prefix;
    use function Utils\Math\double;

    class TextHelper {
        public static function normalize(string $text): string {
            return slug(prefix($text, "msg"));
        }
    }

    function compute(int $v): int {
        return double($v);
    }
}

namespace {
    use App\TextHelper;
    use function App\compute;

    function main() {
        var_dump(TextHelper::normalize("Hello World"));
        var_dump(compute(21));
        echo "done\n";
    }
}
?>
--EXPECT--
string(15) "msg:hello-world"
int(42)
done
