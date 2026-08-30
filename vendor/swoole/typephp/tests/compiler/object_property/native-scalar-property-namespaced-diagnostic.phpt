--TEST--
Native scalar property diagnostics escape namespaced class names in generated C++ strings
--FILE--
<?php
namespace Px\C4129Repro {
    use native_types;

    class NamespacedScalarProperty
    {
        public int $intValue = 0;
        public float $floatValue = 0.0;
        public bool $boolValue = false;

        public function assign(array $config): void
        {
            $this->intValue = $config['int'] ?? 1;
            $this->floatValue = $config['float'] ?? 2.5;
            $this->boolValue = $config['bool'] ?? false;
        }
    }
}

namespace {
    function main(): void
    {
        $box = new \Px\C4129Repro\NamespacedScalarProperty();
        $box->assign(['int' => 12, 'float' => 3.5, 'bool' => true]);

        var_dump($box->intValue, $box->floatValue, $box->boolValue);

        try {
            $box->assign(['int' => 1, 'float' => 2.5, 'bool' => 'invalid']);
        } catch (\TypeError $e) {
            var_dump($e->getMessage());
        }
    }
}
?>
--EXPECT--
int(12)
float(3.5)
bool(true)
string(96) "Cannot assign string to property Px\C4129Repro\NamespacedScalarProperty::$boolValue of type bool"
