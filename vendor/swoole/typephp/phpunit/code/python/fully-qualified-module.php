<?php

namespace App {
    use python\math as mathAlias;

    function pythonFullyQualifiedModule(): mixed
    {
        $root = \python\math\sqrt(16);
        $caseInsensitiveRoot = \Python\math\pi;
        $attributeResult = \Python\math\pi->__str__();
        $nested = \python\os\path\join('/tmp', 'typephp');
        $aliased = mathAlias\sqrt(25);
        $length = \python\len([1, 2, 3]);

        return [$root, $caseInsensitiveRoot, $attributeResult, $nested, $aliased, $length];
    }
}

namespace {
    function main(): void
    {
    }
}
