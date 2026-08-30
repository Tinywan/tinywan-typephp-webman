<?php

namespace App\python {
    function len(array $value): int
    {
        return count($value);
    }

    final class math
    {
        public static function sqrt(int $value): int
        {
            return $value;
        }
    }
}

namespace App {
    function relativePythonNameIsPhpClass(): int
    {
        return python\math::sqrt(16);
    }

    function relativePythonNameIsPhpFunction(): int
    {
        return python\len([1, 2, 3]);
    }
}

namespace {
    function main(): void
    {
    }
}
