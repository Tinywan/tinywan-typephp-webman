<?php

namespace LocalConstantInitializer\Provider {
    const LIMIT = 64;
    const LABEL = 'known';
}

namespace LocalConstantInitializer\Consumer {
    use const LocalConstantInitializer\Provider\LIMIT as IMPORTED_LIMIT;

    const ENABLED = true;

    function localConstantDeclarationInitializer(): void
    {
        $imported = IMPORTED_LIMIT;
        $qualified = \LocalConstantInitializer\Provider\LABEL;
        $namespaced = ENABLED;
        $internal = \PHP_INT_MAX;

        define('LocalConstantInitializer\\Consumer\\RUNTIME_VALUE', 99);
        $runtime = RUNTIME_VALUE;

        // An unqualified internal constant in a namespace can be shadowed by
        // a namespaced define() before this statement executes.
        $namespaceFallback = PHP_VERSION_ID;

        var_dump(
            $imported,
            $qualified,
            $namespaced,
            $internal,
            $runtime,
            $namespaceFallback,
        );
    }
}
