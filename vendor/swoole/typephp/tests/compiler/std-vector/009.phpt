--TEST--
std vector: namespaced self class value unsafe_cast
--FILE--
<?php
namespace StdVectorUnsafeCastNs {
    class Holder
    {
        public static function update($source): void
        {
            $vector = $source->toStdVector(self::class);
            echo "ok\n";
        }

        public static function run(): void
        {
            $vector = std::vector(self::class);
            self::update($vector);
        }
    }
}

namespace {
    function main() {
        StdVectorUnsafeCastNs\Holder::run();
    }
}
?>
--EXPECT--
ok
