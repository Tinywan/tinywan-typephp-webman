--TEST--
Multi-level nested namespaces with classes and functions
--FILE--
<?php
namespace App\Services\Payment {
    class Gateway {
        public static function process(string $type): string {
            return "processed:{$type}";
        }
    }

    function fee(int $amount): int {
        return (int)($amount * 5 / 100);
    }
}

namespace App\Controllers {
    class OrderController {
        public static function create(): string {
            return \App\Services\Payment\Gateway::process("order");
        }
    }
}

namespace {
    function main() {
        var_dump(\App\Services\Payment\Gateway::process("sale"));
        var_dump(\App\Services\Payment\fee(100));
        var_dump(\App\Controllers\OrderController::create());
        echo "done\n";
    }
}
?>
--EXPECT--
string(14) "processed:sale"
int(5)
string(15) "processed:order"
done
