<?php

declare(strict_types=1);

namespace {
    // lib mode owns an embedded module but must not execute the bin entrypoint.
    // This declaration must also be omitted from the published import stub.
    function main(): void
    {
        throw new RuntimeException('lib mode invoked bin main()');
    }
}

namespace TypePhpIntegration\PrivateSupport {
    // The peer provider defines the same non-exported symbol with a different
    // implementation. Both DSOs must retain their own hidden copy.
    #[\NoExport]
    function adjust(int $value): int
    {
        return $value + 1;
    }
}

namespace TypePhpIntegration\Library {
    function add(int $left, int $right): int
    {
        return \TypePhpIntegration\PrivateSupport\adjust($left + $right - 1);
    }

    final class Counter
    {
        public int $value = 0;

        public function add(int $delta): int
        {
            return $this->value += $delta;
        }
    }
}
