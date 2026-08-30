<?php

namespace TypePhp\PythonTools\IdeHelper;

final class PyObjectHelperRenderer
{
    public function render(): string
    {
        $die = '{ die(self::IDE_HELPER_ONLY); }';
        $lines = [
            '<?php',
            '',
            '/**',
            ' * @generated TypePHP Python IDE helper.',
            ' * This file is for IDE indexing and must not be executed or compiled.',
            ' */',
            '',
            'class PyObject implements \\ArrayAccess, \\Iterator, \\Countable',
            '{',
            "    public const IDE_HELPER_ONLY = 'IDE helper only';",
            '',
            '    public function __construct(mixed $value = null) {}',
            '    public function __call(string $name, array $arguments): mixed ' . $die,
            '    public function __get(string $name): mixed ' . $die,
            '    public function __set(string $name, mixed $value): void {}',
            '    public function __unset(string $name): void {}',
            '    public function __toString(): string ' . $die,
            '    public function toArray(): array ' . $die,
            '    public function toValue(): mixed ' . $die,
            '',
            '    /*',
            '     * TypePHP keyword methods are compiler intrinsics.',
            '     * They do not exist on the runtime PyObject class.',
            '     */',
            '    public function toInt(): int ' . $die,
            '    public function toFloat(): float ' . $die,
            '    public function toString(): string ' . $die,
            '    public function toBool(): bool ' . $die,
            '    public function toStream(): mixed ' . $die,
            '    public function toBigInt(): mixed ' . $die,
            '    public function toBigFloat(): mixed ' . $die,
            '    public function toDecimal(): mixed ' . $die,
            '    public function toObject(?string $class = null): object ' . $die,
            '    public function toAny(): mixed ' . $die,
            '    public function toRef(): mixed ' . $die,
            '',
            '    public function __invoke(mixed ...$arguments): mixed ' . $die,
            '    public function offsetGet(mixed $offset): mixed ' . $die,
            '    public function offsetSet(mixed $offset, mixed $value): void {}',
            '    public function offsetUnset(mixed $offset): void {}',
            '    public function offsetExists(mixed $offset): bool ' . $die,
            '    public function key(): mixed ' . $die,
            '    public function next(): void {}',
            '    public function rewind(): void {}',
            '    public function valid(): bool ' . $die,
            '    public function current(): mixed ' . $die,
            '    public function count(): int ' . $die,
            '}',
            'die(PyObject::IDE_HELPER_ONLY);',
            '',
        ];

        return implode(PHP_EOL, $lines);
    }
}
