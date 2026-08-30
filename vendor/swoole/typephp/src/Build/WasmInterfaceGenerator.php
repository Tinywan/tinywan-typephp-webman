<?php

namespace TypePhp\Build;

use RuntimeException;
use TypePhp\Entity\ArgInfo;
use TypePhp\Entity\FunctionDef;
use TypePhp\Type;

/** Generates the stable, tool-neutral contract consumed by PHPX's host bindgen. */
final class WasmInterfaceGenerator
{
    /**
     * @param iterable<FunctionDef> $functions
     * @return array{package:string, world:string, interface:string, functions:list<array<string, mixed>>}
     */
    public function buildManifest(
        iterable $functions,
        string $package,
        string $world,
        string $runtimeProject,
        callable $nativeName,
    ): array {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $runtimeProject)) {
            throw new RuntimeException("Invalid TypePHP runtime project name `{$runtimeProject}`");
        }
        $exports = [];
        $names = [];
        foreach ($functions as $function) {
            if (!$function->wasmExport) {
                continue;
            }
            $displayName = $function->displayName ?: $function->getNamespacedName();
            if ($function->method || $function->stub) {
                throw new RuntimeException("WasmExport function {$displayName}() must have a TypePHP function body");
            }
            if ($function->generator || $function->returnsByRef || $function->hasVariadicArg()) {
                throw new RuntimeException("WasmExport function {$displayName}() cannot be a generator, return by reference, or be variadic");
            }
            if ($function->returnTypeUndeclared) {
                throw new RuntimeException("WasmExport function {$displayName}() must declare a return type");
            }

            $exportName = $function->wasmExportName !== ''
                ? $function->wasmExportName
                : $this->toWitName($function->name);
            $this->assertWitIdentifier($exportName, "WasmExport name for {$displayName}()");
            $key = strtolower($exportName);
            if (isset($names[$key])) {
                throw new RuntimeException(
                    "WasmExport name collision: {$names[$key]}() and {$displayName}() both export `{$exportName}`"
                );
            }
            $names[$key] = $displayName;

            $parameters = [];
            foreach ($function->argInfoList as $argument) {
                if ($argument->byRef || $argument->variadic || $argument->hasDefaultValue()) {
                    throw new RuntimeException(
                        "WasmExport parameter \${$argument->phpName} of {$displayName}() cannot be by-reference, variadic, or optional"
                    );
                }
                $parameters[] = [
                    'name' => $this->toWitName($argument->phpName),
                    'php-name' => $argument->phpName,
                    'cpp-name' => $argument->name,
                    'cpp-type' => $argument->type,
                    'wit-type' => $this->argumentType($argument, $displayName),
                ];
            }

            $exports[] = [
                'name' => $exportName,
                'php-name' => $function->getNamespacedName(),
                'cpp-symbol' => $nativeName($function),
                'parameters' => $parameters,
                'result' => $this->resultType($function, $displayName),
                'result-cpp-type' => $function->returnType,
            ];
        }
        if ($exports === []) {
            throw new RuntimeException('WASI library mode requires at least one #[WasmExport] function');
        }

        return [
            'schema' => 1,
            'package' => $package,
            'world' => $world,
            'interface' => 'api',
            'runtime' => [
                'threading' => 'nts',
                'lifecycle' => 'wit-resource',
                'project' => $runtimeProject,
                'init-symbol' => 'typephp_' . $runtimeProject . '_runtime_init',
                'shutdown-symbol' => 'typephp_' . $runtimeProject . '_runtime_shutdown',
                'error-model' => 'result',
            ],
            'functions' => $exports,
        ];
    }

    /** @param array{package:string, world:string, interface:string, functions:list<array<string, mixed>>} $manifest */
    public function renderWit(array $manifest): string
    {
        $lines = [
            'package ' . $manifest['package'] . ';',
            '',
            'interface ' . $manifest['interface'] . ' {',
            '    record typephp-error {',
            '        class: string,',
            '        message: string,',
            '        code: s64,',
            '    }',
            '',
            '    resource runtime {',
        ];
        foreach ($manifest['functions'] as $function) {
            $parameters = [];
            foreach ($function['parameters'] as $parameter) {
                $parameters[] = $parameter['name'] . ': ' . $parameter['wit-type'];
            }
            $success = $function['result'] === null ? '_' : $function['result'];
            $lines[] = '        ' . $function['name'] . ': func(' . implode(', ', $parameters)
                . ') -> result<' . $success . ', typephp-error>;';
        }
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    create-runtime: func() -> result<runtime, typephp-error>;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'world ' . $manifest['world'] . ' {';
        $lines[] = '    export ' . $manifest['interface'] . ';';
        $lines[] = '}';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * Every TypePHP browser export may transitively call an asynchronous WASI
     * import. Jco must wrap these entry points with WebAssembly.promising or a
     * synchronous-looking PHP call such as RINIT, file I/O, or HTTP cannot
     * suspend through JSPI.
     *
     * @param array<string, mixed> $manifest
     */
    public function renderJcoAsyncExports(array $manifest): string
    {
        [$package, $version] = explode('@', $manifest['package'], 2);
        $interface = $package . '/' . $manifest['interface'] . '@' . $version;
        $exports = [$interface . '#create-runtime'];
        foreach ($manifest['functions'] as $function) {
            $exports[] = $interface . '#[method]runtime.' . $function['name'];
        }
        return implode(PHP_EOL, $exports) . PHP_EOL;
    }

    /**
     * Render the small TypePHP-specific half of the C binding. The generic
     * Canonical ABI half and component type object are emitted by the pinned
     * wit-bindgen version detected in PATH.
     *
     * @param array<string, mixed> $manifest
     */
    public function renderCppAdapter(array $manifest): string
    {
        [$packageNamespace, $packageTail] = explode(':', explode('@', $manifest['package'], 2)[0], 2);
        $packageName = $packageTail;
        $world = $this->cName($manifest['world']);
        $prefix = 'exports_' . $this->cName($packageNamespace) . '_' . $this->cName($packageName)
            . '_' . $this->cName($manifest['interface']);
        $errorType = $prefix . '_typephp_error_t';
        $lines = [
            '#include <cstdlib>',
            '#include <cstring>',
            '#include <exception>',
            '#include <new>',
            '#include <phpx.h>',
            '#include <typephp_helper.h>',
            '#include <typephp_runtime.h>',
            '#include "' . $this->cName($manifest['world']) . '.h"',
            '',
            'TYPEPHP_RUNTIME_INIT_FUNCTION(' . $manifest['runtime']['project'] . ');',
            'TYPEPHP_RUNTIME_SHUTDOWN_FUNCTION(' . $manifest['runtime']['project'] . ');',
            '',
            'struct ' . $prefix . '_runtime_t {',
            '    bool call_active = false;',
            '};',
            '',
            'namespace {',
            'bool runtime_started = false;',
            'bool runtime_failed = false;',
            '',
            'void set_error(' . $errorType . ' *error, const char *class_name, size_t class_len,',
            '               const char *message, size_t message_len, int64_t code = 0) {',
            '    ' . $world . '_string_dup_n(&error->class_, class_name, class_len);',
            '    ' . $world . '_string_dup_n(&error->message, message, message_len);',
            '    error->code = code;',
            '}',
            '',
            'void set_error(' . $errorType . ' *error, const char *message) {',
            '    set_error(error, "TypePHP\\\\WasmError", sizeof("TypePHP\\\\WasmError") - 1,',
            '              message, std::strlen(message));',
            '}',
            '',
            'struct CallGuard {',
            '    bool &active;',
            '    explicit CallGuard(bool &value) : active(value) { active = true; }',
            '    ~CallGuard() { active = false; }',
            '};',
            '',
            'void set_exception(' . $errorType . ' *error, zend_object *exception) {',
            '    zend_class_entry *base = instanceof_function(exception->ce, zend_ce_exception)',
            '        ? zend_ce_exception : zend_ce_error;',
            '    zval message_rv;',
            '    zval code_rv;',
            '    zval *message = zend_read_property_ex(base, exception, ZSTR_KNOWN(ZEND_STR_MESSAGE), true, &message_rv);',
            '    zval *code = zend_read_property_ex(base, exception, ZSTR_KNOWN(ZEND_STR_CODE), true, &code_rv);',
            '    zend_string *class_name = exception->ce->name;',
            '    const char *message_data = Z_TYPE_P(message) == IS_STRING ? Z_STRVAL_P(message) : "PHP exception";',
            '    size_t message_len = Z_TYPE_P(message) == IS_STRING ? Z_STRLEN_P(message) : sizeof("PHP exception") - 1;',
            '    int64_t exception_code = Z_TYPE_P(code) == IS_LONG ? Z_LVAL_P(code) : 0;',
            '    set_error(error, ZSTR_VAL(class_name), ZSTR_LEN(class_name), message_data, message_len, exception_code);',
            '    zend_clear_exception();',
            '}',
            '} // namespace',
            '',
            'extern "C" bool ' . $prefix . '_create_runtime(',
            '    ' . $prefix . '_own_runtime_t *ret, ' . $errorType . ' *error) {',
            '    if (runtime_started) {',
            '        set_error(error, "Only one TypePHP runtime may be active in an NTS component instance");',
            '        return false;',
            '    }',
            '    if (runtime_failed) {',
            '        set_error(error, "The TypePHP runtime is unavailable after an earlier fatal error");',
            '        return false;',
            '    }',
            '    char program[] = "typephp-component";',
            '    char *argv[] = {program, nullptr};',
            '    if (TYPEPHP_RUNTIME_INIT(' . $manifest['runtime']['project'] . ')(1, argv) != 0) {',
            '        runtime_failed = true;',
            '        set_error(error, "Unable to initialize the TypePHP runtime");',
            '        return false;',
            '    }',
            '    auto *runtime = new (std::nothrow) ' . $prefix . '_runtime_t();',
            '    if (runtime == nullptr) {',
            '        TYPEPHP_RUNTIME_SHUTDOWN(' . $manifest['runtime']['project'] . ')();',
            '        set_error(error, "Unable to allocate the TypePHP runtime resource");',
            '        return false;',
            '    }',
            '    runtime_started = true;',
            '    *ret = ' . $prefix . '_runtime_new(runtime);',
            '    return true;',
            '}',
            '',
            'extern "C" void ' . $prefix . '_runtime_destructor(' . $prefix . '_runtime_t *runtime) {',
            '    delete runtime;',
            '    if (runtime_started) {',
            '        TYPEPHP_RUNTIME_SHUTDOWN(' . $manifest['runtime']['project'] . ')();',
            '    }',
            '    runtime_started = false;',
            '    runtime_failed = false;',
            '}',
            '',
        ];

        foreach ($manifest['functions'] as $function) {
            $returnType = $function['result'];
            $declaration = [$prefix . '_borrow_runtime_t self'];
            $callArguments = [];
            foreach ($function['parameters'] as $parameter) {
                [$base, $nullable] = $this->splitWitType($parameter['wit-type']);
                $cType = $this->cAbiType($base, $world);
                $cName = $this->cName($parameter['name']);
                if ($base === 'string' || $nullable) {
                    $declaration[] = $cType . ' *' . ($nullable ? 'maybe_' : '') . $cName;
                } else {
                    $declaration[] = $cType . ' ' . $cName;
                }
                $callArguments[] = $this->cppArgument($parameter, $cName, $base, $nullable);
            }
            if ($returnType !== null) {
                [$returnBase, $returnNullable] = $this->splitWitType($returnType);
                $declaration[] = ($returnNullable
                    ? $world . '_option_' . $this->cName($returnBase) . '_t'
                    : $this->cAbiType($returnBase, $world)) . ' *ret';
            } else {
                $returnBase = '';
                $returnNullable = false;
            }
            $declaration[] = $errorType . ' *error';

            $lines[] = 'extern ' . $function['result-cpp-type'] . ' ' . $function['cpp-symbol']
                . '(' . implode(', ', array_map(
                    static fn (array $parameter): string => $parameter['cpp-type'] . ' ' . $parameter['cpp-name'],
                    $function['parameters'],
                )) . ');';
            $lines[] = '';
            $lines[] = 'extern "C" bool ' . $prefix . '_method_runtime_' . $this->cName($function['name'])
                . '(' . implode(', ', $declaration) . ') {';
            $lines[] = '    if (self == nullptr || !runtime_started || runtime_failed) {';
            $lines[] = '        set_error(error, "The TypePHP runtime resource is closed or unavailable");';
            $lines[] = '        return false;';
            $lines[] = '    }';
            $lines[] = '    if (self->call_active) {';
            $lines[] = '        set_error(error, "Concurrent or reentrant calls on one NTS TypePHP component are not supported");';
            $lines[] = '        return false;';
            $lines[] = '    }';
            $lines[] = '    CallGuard call_guard(self->call_active);';
            $lines[] = '    bool success = false;';
            $lines[] = '    zend_try {';
            $lines[] = '        try {';
            $call = $function['cpp-symbol'] . '(' . implode(', ', $callArguments) . ')';
            if ($returnType === null) {
                $lines[] = '            ' . $call . ';';
            } else {
                $lines[] = '            auto result = ' . $call . ';';
                foreach ($this->cppResult($returnBase, $returnNullable, $world) as $resultLine) {
                    $lines[] = '            ' . $resultLine;
                }
            }
            $lines[] = '            success = true;';
            $lines[] = '        } catch (zend_object *exception) {';
            $lines[] = '            set_exception(error, exception);';
            $lines[] = '        } catch (const std::exception &exception) {';
            $lines[] = '            set_error(error, exception.what());';
            $lines[] = '        } catch (...) {';
            $lines[] = '            set_error(error, "Unknown C++ exception");';
            $lines[] = '        }';
            $lines[] = '    } zend_catch {';
            $lines[] = '        runtime_failed = true;';
            $lines[] = '        set_error(error, "Zend bailout while executing the exported function");';
            $lines[] = '    } zend_end_try();';
            $lines[] = '    return success;';
            $lines[] = '}';
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /** @return array{string, bool} */
    private function splitWitType(string $type): array
    {
        if (str_starts_with($type, 'option<') && str_ends_with($type, '>')) {
            return [substr($type, 7, -1), true];
        }
        return [$type, false];
    }

    private function cAbiType(string $type, string $world): string
    {
        return match ($type) {
            'bool' => 'bool',
            's64' => 'int64_t',
            'f64' => 'double',
            'string' => $world . '_string_t',
            default => throw new RuntimeException("Unsupported WIT C ABI type `{$type}`"),
        };
    }

    /** @param array<string, mixed> $parameter */
    private function cppArgument(array $parameter, string $name, string $base, bool $nullable): string
    {
        if (!$nullable) {
            return match ($base) {
                'string' => 'php::Str(reinterpret_cast<const char *>(' . $name . '->ptr), ' . $name . '->len)',
                default => $name,
            };
        }
        $pointer = 'maybe_' . $name;
        $value = match ($base) {
            'string' => 'php::Str(reinterpret_cast<const char *>(' . $pointer . '->ptr), ' . $pointer . '->len)',
            default => '*' . $pointer,
        };
        return '(' . $pointer . ' == nullptr ? php::Var(php::null) : php::Var(' . $value . '))';
    }

    /** @return list<string> */
    private function cppResult(string $base, bool $nullable, string $world): array
    {
        if ($nullable) {
            $lines = [
                'ret->is_some = !result.isNull();',
                'if (ret->is_some) {',
            ];
            if ($base === 'string') {
                $lines[] = '    php::Str string_result = php::toString(result);';
                $lines[] = '    ' . $world . '_string_dup_n(&ret->val, string_result.data(), string_result.length());';
                $lines[] = '}';
                return $lines;
            }
            $assignment = match ($base) {
                'bool' => 'ret->val = php::toBool(result);',
                's64' => 'ret->val = php::toInt(result);',
                'f64' => 'ret->val = php::toFloat(result);',
                default => throw new RuntimeException("Unsupported nullable WIT result `{$base}`"),
            };
            $lines[] = '    ' . $assignment;
            $lines[] = '}';
            return $lines;
        }
        return [match ($base) {
            'bool' => '*ret = php::toBool(result);',
            's64' => '*ret = php::toInt(result);',
            'f64' => '*ret = php::toFloat(result);',
            'string' => $world . '_string_dup_n(ret, result.data(), result.length());',
            default => throw new RuntimeException("Unsupported WIT result `{$base}`"),
        }];
    }

    private function cName(string $name): string
    {
        return strtolower(str_replace('-', '_', $name));
    }

    private function argumentType(ArgInfo $argument, string $function): string
    {
        $type = $this->scalarType(
            $argument->type,
            "parameter \${$argument->phpName} of {$function}()",
            $argument->typeStr,
        );
        return $argument->nullable ? "option<{$type}>" : $type;
    }

    private function resultType(FunctionDef $function, string $displayName): ?string
    {
        if ($function->returnType === Type::VOID) {
            return null;
        }
        $nullable = str_starts_with($function->returnTypeStr, '?')
            || str_contains(strtolower($function->returnTypeStr), 'null');
        $type = $this->scalarType($function->returnType, "return type of {$displayName}()", $function->returnTypeStr);
        return $nullable ? "option<{$type}>" : $type;
    }

    private function scalarType(string $type, string $location, string $declaredType = ''): string
    {
        $mapped = match ($type) {
            Type::BOOL => 'bool',
            Type::INT => 's64',
            Type::FLOAT => 'f64',
            Type::STR => 'string',
            default => null,
        };
        if ($mapped !== null) {
            return $mapped;
        }
        $normalized = strtolower(str_replace(['?', '|null', 'null|'], '', $declaredType));
        $mapped = match ($normalized) {
            'bool' => 'bool',
            'int' => 's64',
            'float' => 'f64',
            'string' => 'string',
            default => null,
        };
        if ($mapped !== null) {
            return $mapped;
        }
        throw new RuntimeException(
            "Unsupported WasmExport {$location}; the first release supports bool, int, float, string, nullable scalars, and void"
        );
    }

    private function toWitName(string $name): string
    {
        $name = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', $name) ?? $name;
        return strtolower(str_replace('_', '-', $name));
    }

    private function assertWitIdentifier(string $name, string $location): void
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $name) !== 1) {
            throw new RuntimeException("{$location} must be a lowercase WIT identifier (for example `greet-user`)");
        }
    }
}
