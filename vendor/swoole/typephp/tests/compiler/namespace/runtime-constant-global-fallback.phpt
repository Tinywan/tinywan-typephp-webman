--TEST--
Unqualified runtime constants in a namespace fall back to global constants
--FILE--
<?php

namespace RuntimeConstantFallback {
    class InternalConstantTernary
    {
        private bool $useLimit = true;
        private array $values = [1, 2, 3];

        public function read(): int
        {
            // Reading the property introduces branch cleanup in generated C++.
            // The unqualified constant must still be treated as a dynamic value:
            // a namespaced definition can shadow PHP's global internal constant.
            return $this->useLimit ? PHP_INT_MAX : count($this->values);
        }
    }

    function readGlobalOnly()
    {
        return GLOBAL_ONLY;
    }

    function readPreferred()
    {
        return PREFERRED;
    }

    function readInternalOverride()
    {
        return PHP_VERSION;
    }
}

namespace {
    function main(): void
    {
        define('GLOBAL_ONLY', 'global');
        define('PREFERRED', 'global-preferred');
        define('RuntimeConstantFallback\Preferred', 'wrong-case-name');
        define('RuntimeConstantFallback\PREFERRED', 'namespaced');
        define('RuntimeConstantFallback\PHP_VERSION', 'runtime-override');
        define('RuntimeConstantFallback\PHP_INT_MAX', 42);

        var_dump(\RuntimeConstantFallback\readGlobalOnly());
        var_dump(\RuntimeConstantFallback\readPreferred());
        var_dump(\RuntimeConstantFallback\readInternalOverride());
        var_dump((new \RuntimeConstantFallback\InternalConstantTernary())->read());
    }
}
?>
--EXPECT--
string(6) "global"
string(10) "namespaced"
string(16) "runtime-override"
int(42)
