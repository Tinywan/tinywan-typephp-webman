<?php

declare(strict_types=1);

namespace {
    function main(): void
    {
        throw new RuntimeException('peer lib mode invoked bin main()');
    }
}

namespace TypePhpIntegration\PrivateSupport {
    // The primary provider deliberately defines the same hidden PHP/C++ symbol.
    // Linking both libraries verifies that private implementation symbols bind
    // locally instead of being interposed by the other provider.
    #[\NoExport]
    function adjust(int $value): int
    {
        return $value * 3;
    }
}

namespace TypePhpIntegration\PeerLibrary {
    function scale(int $value): int
    {
        return \TypePhpIntegration\PrivateSupport\adjust($value);
    }

    final class Label
    {
        public function __construct(private string $value)
        {
        }

        public function render(): string
        {
            return '[' . $this->value . ']';
        }
    }
}
