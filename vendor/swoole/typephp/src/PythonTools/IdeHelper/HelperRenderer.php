<?php

namespace TypePhp\PythonTools\IdeHelper;

final class HelperRenderer
{
    /** @var array<string, true> */
    private const RESERVED = [
        '__halt_compiler' => true, 'abstract' => true, 'and' => true, 'array' => true,
        'as' => true, 'bool' => true, 'break' => true, 'callable' => true, 'case' => true,
        'catch' => true, 'class' => true, 'clone' => true, 'const' => true, 'continue' => true,
        'declare' => true, 'default' => true, 'die' => true, 'do' => true, 'echo' => true,
        'else' => true, 'elseif' => true, 'empty' => true, 'enddeclare' => true,
        'endfor' => true, 'endforeach' => true, 'endif' => true, 'endswitch' => true,
        'endwhile' => true, 'enum' => true, 'eval' => true, 'exit' => true, 'extends' => true,
        'false' => true, 'final' => true, 'finally' => true, 'float' => true, 'fn' => true,
        'for' => true, 'foreach' => true, 'from' => true, 'function' => true, 'global' => true,
        'goto' => true, 'if' => true, 'implements' => true, 'include' => true,
        'include_once' => true, 'instanceof' => true, 'insteadof' => true, 'int' => true,
        'interface' => true, 'isset' => true, 'iterable' => true, 'list' => true,
        'match' => true, 'mixed' => true, 'namespace' => true, 'never' => true, 'new' => true,
        'null' => true, 'object' => true, 'or' => true, 'parent' => true, 'print' => true,
        'private' => true, 'protected' => true, 'public' => true, 'readonly' => true,
        'require' => true, 'require_once' => true, 'resource' => true, 'return' => true,
        'self' => true, 'static' => true, 'string' => true, 'switch' => true, 'throw' => true,
        'trait' => true, 'true' => true, 'try' => true, 'unset' => true, 'use' => true,
        'var' => true, 'void' => true, 'while' => true, 'xor' => true, 'yield' => true,
    ];

    /**
     * Render declarations inside an unreachable branch. IDEs can index them,
     * while accidentally including the helper has no runtime side effects.
     *
     * @param array<string, mixed> $metadata
     */
    public function render(array $metadata): string
    {
        $module = (string) $metadata['module'];
        $namespace = $module === 'builtins'
            ? 'python'
            : 'python\\' . str_replace('.', '\\', $module);
        $lines = [
            '<?php',
            '',
            '/**',
            ' * @generated TypePHP Python IDE helper.',
            ' * This file is for IDE indexing and must not be executed or compiled.',
            ' */',
            '',
            'namespace ' . $namespace . ';',
            '',
        ];

        $seenFunctions = [];
        foreach ($metadata['attributes'] ?? [] as $attribute) {
            $name = (string) ($attribute['name'] ?? '');
            if (!$this->isDeclarableName($name)) {
                $lines[] = '// Omitted Python attribute with an invalid PHP identifier: ' . $this->comment($name);
                continue;
            }
            $lines[] = 'const ' . $name . ' = new \\PyObject();';
            $lines[] = '';
        }

        foreach ($metadata['functions'] ?? [] as $function) {
            $name = (string) ($function['name'] ?? '');
            $folded = strtolower($name);
            if (!$this->isDeclarableName($name) || isset($seenFunctions[$folded])) {
                $lines[] = '// Omitted Python callable not representable as a PHP function: ' . $this->comment($name);
                continue;
            }
            $seenFunctions[$folded] = true;
            $lines[] = 'function ' . $name . '(' . $this->renderParameters($function['parameters'] ?? [])
                . '): \\PyObject ' . $this->unreachableBody();
            $lines[] = '';
        }

        $seenClasses = [];
        foreach ($metadata['classes'] ?? [] as $class) {
            $name = (string) ($class['name'] ?? '');
            $folded = strtolower($name);
            if (!$this->isDeclarableName($name) || isset($seenClasses[$folded])) {
                $lines[] = '// Omitted Python class not representable as a PHP class: ' . $this->comment($name);
                continue;
            }
            $seenClasses[$folded] = true;
            if (!isset($seenFunctions[$folded])) {
                $seenFunctions[$folded] = true;
                $lines[] = 'function ' . $name . '(' . $this->renderParameters($class['parameters'] ?? [])
                    . '): ' . $name . ' ' . $this->unreachableBody();
                $lines[] = '';
            }
            $properties = $class['properties'] ?? [];
            if ($properties !== []) {
                $lines[] = '/**';
                foreach ($properties as $property) {
                    if ($this->isValidIdentifier((string) $property)) {
                        $lines[] = ' * @property \\PyObject $' . $property;
                    }
                }
                $lines[] = ' */';
            }
            $lines[] = 'class ' . $name . ' extends \\PyObject';
            $lines[] = '{';
            $lines[] = '    public function __construct(' . $this->renderParameters($class['parameters'] ?? [])
                . ') { parent::__construct(); }';
            $seenMethods = [];
            foreach ($class['methods'] ?? [] as $method) {
                $methodName = (string) ($method['name'] ?? '');
                $methodFolded = strtolower($methodName);
                if ($methodFolded === 'count') {
                    $lines[] = "    // Python count() conflicts with PyObject::count(); use __call('count', [...]).";
                    continue;
                }
                if (!$this->isValidIdentifier($methodName) || isset($seenMethods[$methodFolded])) {
                    continue;
                }
                $seenMethods[$methodFolded] = true;
                $lines[] = '    public function ' . $methodName . '('
                    . $this->renderParameters($method['parameters'] ?? []) . '): \\PyObject '
                    . $this->unreachableBody();
            }
            $lines[] = '}';
            $lines[] = '';
        }

        $lines[] = 'die(\\PyObject::IDE_HELPER_ONLY);';
        $lines[] = '';
        return implode(PHP_EOL, $lines);
    }

    /** @param list<array{name: string, optional?: bool, variadic?: bool}> $parameters */
    private function renderParameters(array $parameters): string
    {
        $regular = [];
        $variadic = null;
        $optionalSeen = false;
        foreach ($parameters as $index => $parameter) {
            $name = (string) ($parameter['name'] ?? ('arg' . $index));
            if (!$this->isValidIdentifier($name) || $name === 'this') {
                $name = 'arg' . $index;
            }
            if (!empty($parameter['variadic'])) {
                $variadic ??= 'mixed ...$' . $name;
                continue;
            }
            $optional = $optionalSeen || !empty($parameter['optional']);
            $optionalSeen = $optional;
            $regular[] = 'mixed $' . $name . ($optional ? ' = null' : '');
        }
        if ($variadic !== null) {
            $regular[] = $variadic;
        }
        return implode(', ', $regular);
    }

    private function isDeclarableName(string $name): bool
    {
        return $this->isValidIdentifier($name) && !isset(self::RESERVED[strtolower($name)]);
    }

    private function isValidIdentifier(string $name): bool
    {
        return preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/D', $name) === 1;
    }

    private function comment(string $value): string
    {
        return str_replace(["\r", "\n", '*/'], [' ', ' ', '* /'], $value);
    }

    private function unreachableBody(): string
    {
        return '{ die(\\PyObject::IDE_HELPER_ONLY); }';
    }
}
