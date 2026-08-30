<?php

namespace App {
    use function python\len;
    use function python\abs as py_abs;
    use function python\math\sqrt as py_sqrt;
    use const python\math\pi as py_pi;

    function pythonImportedSymbols(): array
    {
        return [
            len([1, 2, 3]),
            py_abs(-4),
            py_sqrt(16),
            py_pi,
        ];
    }
}

namespace {
    function main(): void
    {
    }
}
