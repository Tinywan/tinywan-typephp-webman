<?php
/**
 * This file is part of TypePHP.
 *
 * Call argument lowering shared by native and dynamic call paths.
 */

namespace TypePhp\Generator;

use TypePhp\Type;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeAbstract;
use TypePhp\Entity\ArgInfo;
use TypePhp\Entity\FunctionDef;
use TypePhp\Exception\PlaceHolder;
use TypePhp\Resolver\Reflection;
use TypePhp\Generator\Symbol;

trait CallArgumentGenerator
{
    protected function parseNativeCallArgs(
        array $callArgs,
        string $nativeFunc,
        int $parameterOffset = 0,
        bool $deferTrailingDefaults = false,
    ): string
    {
        $functionDef = $this->getFunction($nativeFunc);
        $providedArgs = [];
        $defaultArgs = [];
        $sourceArgs = [];
        $variadicArgCount = 0;
        $hasNamedArg = false;
        $argNameIndex = $this->getFunctionArgNameIndex($functionDef);
        $variadicArgIndex = $this->getVariadicArgIndex($functionDef);
        // 对命名参数进行重排
        foreach ($callArgs as $i => $arg) {
            if ($this->isPlaceholderExpr($arg)) {
                throw new PlaceHolder();
            }
            if ($arg->name) {
                $argName = $arg->name->name;
                $k = $argNameIndex[$argName] ?? null;
                if ($k !== null and ($variadicArgIndex === null or $k < $variadicArgIndex)) {
                    if ($k < $parameterOffset) {
                        $this->fatalError($arg, 'Named argument cannot target the extension receiver');
                    }
                    $providedArgs[$k] = true;
                    $sourceArgs[] = [$k, null, $arg];
                } else {
                    if ($variadicArgIndex === null) {
                        $this->fatalError($arg, "Unknown named argument `{$argName}`");
                    }
                    $sourceArgs[] = [$variadicArgIndex, $argName, $arg];
                    $variadicArgCount++;
                }
                $hasNamedArg = true;
            } elseif ($variadicArgIndex !== null and $i + $parameterOffset >= $variadicArgIndex) {
                $sourceArgs[] = [$variadicArgIndex, null, $arg];
                $variadicArgCount++;
            } else {
                $argIndex = $i + $parameterOffset;
                $providedArgs[$argIndex] = true;
                $sourceArgs[] = [$argIndex, null, $arg];
            }
        }
        // Fill ABI holes first, but do not sort yet. User expressions must be
        // lowered in source order; sorting raw AST arguments here would also
        // reorder their side effects.
        if ($hasNamedArg) {
            $lastProvidedIndex = $providedArgs === []
                ? $parameterOffset - 1
                : max(array_keys($providedArgs));
            if ($deferTrailingDefaults && $variadicArgCount > 0) {
                $lastProvidedIndex = $variadicArgIndex;
            }
            // 命名参数中间存在空洞，需要使用默认参数填充
            foreach ($functionDef->argInfoList as $k => $argInfo) {
                if ($k < $parameterOffset) {
                    continue;
                }
                if ($variadicArgIndex !== null and $k === $variadicArgIndex) {
                    continue;
                }
                if (!isset($providedArgs[$k])) {
                    // A Native virtual overload must omit a trailing default
                    // so the dynamically selected implementation supplies it.
                    // A named-argument hole before a later argument cannot be
                    // represented by a positional C++ overload without a
                    // presence mask, so reject that uncommon shape explicitly.
                    if ($deferTrailingDefaults && $k > $lastProvidedIndex) {
                        continue;
                    }
                    if ($deferTrailingDefaults) {
                        $this->fatalError(
                            reset($callArgs),
                            'Named calls to Native virtual methods cannot skip an earlier optional parameter',
                        );
                    }
                    if (!$argInfo->hasDefaultValue()) {
                        $errorNode = null;
                        foreach ($callArgs as $a) {
                            if ($a instanceof Node\Arg && $a->name) {
                                $errorNode = $a;
                                break;
                            }
                        }
                        $argName = $argInfo->phpName ?: $this->unescapeVarName($argInfo->name);
                        $this->fatalError($errorNode ?? reset($callArgs), 'Named argument `' . $argName . '` is missing default value');
                    }
                    // Defaults are resolved in the declaration scope. Re-parsing
                    // the original AST here would evaluate self/parent/private
                    // class constants in the caller's scope instead.
                    $defaultArgs[$k] = $this->genDefaultArgumentExpr($nativeFunc, $k);
                }
            }
        }

        // 函数只接受一个变长参数，且调用参数为空，直接传入空数组
        if (count($sourceArgs) === 0
            and count($functionDef->argInfoList) === $parameterOffset + 1
            and $functionDef->argInfoList[$parameterOffset]->variadic) {
            return $deferTrailingDefaults ? '' : '{}';
        }

        $resolvedArgs = [];
        $variadicVar = null;
        $callableName = $functionDef->displayName ?: $functionDef->getNamespacedName();

        // Evaluate every supplied argument in PHP source order. The resulting
        // expressions/temporaries may then be rearranged safely for the native
        // C++ ABI without changing observable call order.
        foreach ($sourceArgs as [$argIndex, $variadicName, $arg]) {
            if ($argIndex !== $variadicArgIndex) {
                $argInfo = $this->getArgInfo($arg, $nativeFunc, $argIndex);
                $resolvedArgs[$argIndex] = $this->getTypeConvertedArg(
                    $arg,
                    $argInfo,
                    $callableName,
                    $argIndex
                );
                continue;
            }

            // A single unpacked native array is already the ABI value. Reuse
            // it directly instead of allocating and merging a temporary array.
            if ($variadicArgCount === 1 && $arg->unpack && $this->isVarExpr($arg->value)) {
                $var = $this->parseIdentifier($arg->value);
                if ($this->getVarType($var) === Type::ARRAY) {
                    $resolvedArgs[$variadicArgIndex] = $var;
                    continue;
                }
            }

            $variadicVar ??= $this->addTmpVar(Type::ARRAY);
            $argInfo = $functionDef->argInfoList[$variadicArgIndex];
            if ($arg->unpack) {
                $this->context->beforeStmtLines[] = $variadicVar . '.merge(' . $this->parseArrayArg($arg) . ');';
            } elseif ($variadicName !== null) {
                $value = $this->getTypeConvertedArg($arg, $argInfo, $callableName, $variadicArgIndex);
                $this->context->beforeStmtLines[] = $variadicVar . '.setValue('
                    . $this->getLiteralString($variadicName) . ', ' . $value . ');';
            } else {
                $value = $this->getTypeConvertedArg($arg, $argInfo, $callableName, $variadicArgIndex);
                $this->context->beforeStmtLines[] = $variadicVar . '.appendValue(' . $value . ');';
            }
        }

        // Defaults have no caller-side evaluation. Add them after user
        // arguments, then sort only the already-lowered values for the ABI.
        foreach ($defaultArgs as $i => $defaultArg) {
            $resolvedArgs[$i] = $defaultArg;
        }
        if ($variadicVar !== null) {
            $resolvedArgs[$variadicArgIndex] = $variadicVar;
        }
        ksort($resolvedArgs);
        return implode(', ', $resolvedArgs);
    }

    protected function isReferenceArgument($funcName, $className, $argIndex): bool
    {
        $argInfo = $this->getAotCallArgInfo($funcName, $className, $argIndex);
        if ($argInfo !== null) {
            return $argInfo->byRef;
        }

        if ($className) {
            // 动态调用类方法，无法判断参数是否为引用
            if ($className === self::DYNAMIC_CALLED_CLASS) {
                return false;
            }
            $param = Reflection::getClassMethodParameter($className, $funcName, $argIndex);
        } else {
            $param = Reflection::getFunctionParameter($funcName, $argIndex);
        }

        if ($param) {
            return $param->isPassedByReference();
        }

        // 参数索引超出声明范围，检查最后一个参数是否为变长引用参数（如 &...$rest）
        $variadicParam = Reflection::getVariadicParameter($funcName, $className);
        return $variadicParam !== null && $variadicParam->isPassedByReference();
    }

    protected function getAotCallArgInfo(string $funcName, string $className, int $argIndex): ?ArgInfo
    {
        if ($className !== '') {
            $functionDef = $this->findAotMethodFunctionDef($className, $funcName);
            if ($functionDef === null) {
                return null;
            }
            return $this->getArgInfoByIndex($functionDef, $argIndex);
        }

        if (!$this->hasFunction($funcName)) {
            return null;
        }
        return $this->getArgInfoByIndex($this->getFunction($funcName), $argIndex);
    }

    protected function getAotCallArgInfoByName(string $funcName, string $className, string $argName): ?ArgInfo
    {
        $functionDef = null;
        if ($className !== '') {
            $functionDef = $this->findAotMethodFunctionDef($className, $funcName);
        } elseif ($this->hasFunction($funcName)) {
            $functionDef = $this->getFunction($funcName);
        }

        if ($functionDef === null) {
            return null;
        }

        $variadicArgInfo = null;
        foreach ($functionDef->argInfoList as $argInfo) {
            if ($argInfo->variadic) {
                $variadicArgInfo = $argInfo;
            }
            if (($argInfo->phpName ?: $this->unescapeVarName($argInfo->name)) === $argName) {
                return $argInfo;
            }
        }
        return $variadicArgInfo;
    }

    /** Resolve a project class or interface method declaration for AOT call arguments. */
    protected function findAotMethodFunctionDef(string $className, string $funcName): ?FunctionDef
    {
        if ($className === self::DYNAMIC_CALLED_CLASS) {
            return null;
        }

        if ($this->hasInterface($className)) {
            return $this->findAotInterfaceMethodFunctionDef($className, $funcName);
        }

        if (!$this->hasClass($className)) {
            return null;
        }

        $classDef = $this->getClass($className);
        while (true) {
            if ($classDef->hasMethod($funcName)) {
                return $classDef->getMethod($funcName)->functionDef;
            }
            if ($classDef->hasAbstractMethod($funcName)) {
                return $classDef->getAbstractMethod($funcName)->functionDef;
            }
            foreach ($classDef->implements as $interface) {
                $functionDef = $this->findAotInterfaceMethodFunctionDef($interface, $funcName);
                if ($functionDef !== null) {
                    return $functionDef;
                }
            }
            if (!$classDef->extends || !$this->hasClass($classDef->extends)) {
                return null;
            }
            $classDef = $this->getClass($classDef->extends);
        }
    }

    /** Resolve a method from an interface or one of its parent interfaces. */
    protected function findAotInterfaceMethodFunctionDef(string $interfaceName, string $funcName): ?FunctionDef
    {
        $pending = [$interfaceName];
        $visited = [];

        while ($pending) {
            $current = array_pop($pending);
            $key = strtolower($current);
            if (isset($visited[$key]) || !$this->hasInterface($current)) {
                continue;
            }
            $visited[$key] = true;

            $interfaceDef = $this->getInterface($current);
            if ($interfaceDef->hasMethod($funcName)) {
                return $interfaceDef->methods[strtolower($funcName)]->functionDef;
            }
            foreach ($interfaceDef->extendsList ?: ($interfaceDef->extends ? [$interfaceDef->extends] : []) as $parent) {
                $pending[] = $parent;
            }
        }

        return null;
    }

    protected function getArgInfoByIndex(FunctionDef $functionDef, int $argIndex): ?ArgInfo
    {
        if (array_key_exists($argIndex, $functionDef->argInfoList)) {
            return $functionDef->argInfoList[$argIndex];
        }
        if ($functionDef->hasVariadicArg()) {
            return $functionDef->argInfoList[array_key_last($functionDef->argInfoList)];
        }
        return null;
    }

    protected function isReferenceNamedArgument(string $funcName, string $className, string $argName): bool
    {
        $argInfo = $this->getAotCallArgInfoByName($funcName, $className, $argName);
        if ($argInfo !== null) {
            return $argInfo->byRef;
        }

        if ($className) {
            if ($className === self::DYNAMIC_CALLED_CLASS) {
                return false;
            }
            $ref = Reflection::getClass($className);
            if (!$ref) {
                return false;
            }
            try {
                $params = $ref->getMethod($funcName)->getParameters();
            } catch (\ReflectionException) {
                return false;
            }
        } else {
            $ref = Reflection::getFunction($funcName);
            if (!$ref) {
                return false;
            }
            $params = $ref->getParameters();
        }

        $variadicParam = null;
        foreach ($params as $param) {
            if ($param->isVariadic()) {
                $variadicParam = $param;
            }
            if ($param->getName() === $argName) {
                return $param->isPassedByReference();
            }
        }
        return $variadicParam !== null && $variadicParam->isPassedByReference();
    }

    protected function parseCallArgs(
        array $args,
        string $funcName = '',
        string $className = '',
        bool $separateNamedArgs = true,
        bool $forceArrayArgs = false,
        bool $preserveExistingReferences = false
    ): string
    {
        $list_args = [];
        $arrayArgsVar = null;
        $argsVar = null;
        $namedArgsVar = null;
        $namedArgs = [];
        $hasNamedArg = false;
        $hasUnpack = false;

        if ($forceArrayArgs) {
            $this->ensureCallArrayArgs($arrayArgsVar, $list_args);
        }

        foreach ($args as $i => $arg) {
            if ($this->isPlaceholderExpr($arg)) {
                throw new PlaceHolder();
            }
            if ($arg->unpack) {
                if ($hasNamedArg) {
                    $this->fatalError($arg, 'Cannot use argument unpacking after named arguments');
                }
                $hasUnpack = true;
                if (!$forceArrayArgs && $separateNamedArgs) {
                    $callArgs = $this->ensureCallArgs($argsVar, $list_args);
                    $this->context->beforeStmtLines[] = $callArgs . '.appendUnpacked(' . $this->parseArrayArg($arg) . ');';
                    if ($arg->getAttribute(self::ATTR_SCOPED_CALLBACK) === 'normalize-unpacked') {
                        $this->context->beforeStmtLines[] = 'php::normalizeCallableClass('
                            . $callArgs . ', 0, ' . $this->getCallableScopeExpr() . ');';
                    }
                } else {
                    $arrayArgs = $this->ensureCallArrayArgs($arrayArgsVar, $list_args);
                    $this->context->beforeStmtLines[] = $arrayArgs . '.merge(' . $this->parseArrayArg($arg) . ');';
                }
                continue;
            }
            if ($arg->name !== null) {
                $hasNamedArg = true;
                if (!$this->isIdExpr($arg->name)) {
                    $this->fatalError($arg, 'Named argument must be a string');
                }
                if (array_key_exists($arg->name->name, $namedArgs)) {
                    $this->fatalError($arg, "Duplicate named argument `{$arg->name->name}`");
                }
                $namedArgs[$arg->name->name] = true;
                $byRef = ($funcName && $this->isReferenceNamedArgument($funcName, $className, $arg->name->name))
                    || ($preserveExistingReferences && $this->isExistingReferenceCallArg($arg));
                if ($byRef) {
                    $this->assertReadonlyPropertyReferenceForbidden($arg->value, $arg, false);
                }
                $value = ($byRef || $this->isRefvalCall($arg->value) || $this->isToRefCall($arg->value))
                    ? $this->parseReferenceCallArgValue($arg)
                    : $this->parseCallArgValue($arg);
                $value = $this->wrapScopedCallbackArg($arg, $value);
                if ($separateNamedArgs) {
                    $namedArgsArray = $this->ensureCallNamedArgs($namedArgsVar);
                    $this->context->beforeStmtLines[] = $namedArgsArray . '.set(' . $this->getLiteralString($arg->name->name) . ', ' . $value . ');';
                } else {
                    $arrayArgs = $this->ensureCallArrayArgs($arrayArgsVar, $list_args);
                    $this->context->beforeStmtLines[] = $arrayArgs . '.set(' . $this->getLiteralString($arg->name->name) . ', ' . $value . ');';
                }
                continue;
            }
            if ($hasNamedArg) {
                $this->fatalError($arg, 'Cannot use positional argument after named argument');
            }
            if ($hasUnpack) {
                $this->fatalError($arg, 'Cannot use positional argument after argument unpacking');
            }
            $byRef = ($funcName && $this->isReferenceArgument($funcName, $className, $i))
                || ($preserveExistingReferences && $this->isExistingReferenceCallArg($arg));
            if ($byRef) {
                $this->assertReadonlyPropertyReferenceForbidden($arg->value, $arg, false);
            }
            $scopedCallback = $arg->getAttribute(self::ATTR_SCOPED_CALLBACK);
            if ($scopedCallback !== null) {
                if ($this->isVarExpr($arg->value)) {
                    $name = $this->parseIdentifier($arg->value);
                    if (!$this->hasVar($name)) {
                        $this->fatalError($arg, 'Undefined variable `$' . $name . '`');
                    }
                }
                $value = $this->wrapScopedCallbackArg($arg, $this->parseCallArgValue($arg));
                $this->addPositionalCallArg($value, $arrayArgsVar, $list_args);
                continue;
            }
            if ($this->isVarExpr($arg->value)) {
                $name = $this->parseIdentifier($arg->value);
                if ($byRef) {
                    $this->addPositionalCallArg($this->parseArgRefVar($arg, $name), $arrayArgsVar, $list_args);
                    continue;
                }
                if (!$this->hasVar($name)) {
                    $this->fatalError($arg, 'Undefined variable `$' . $name . '`');
                }
            } elseif ($this->isPropertyFetch($arg->value)) {
                if ($byRef) {
                    $this->addPositionalCallArg($this->emitDynamicPropertyFetchRef($arg->value, $arg), $arrayArgsVar, $list_args);
                    continue;
                }
                if ($this->isVarExpr($arg->value->var)) {
                    $objectExpr = $this->parseIdentifier($arg->value->var);
                    if (!$this->hasVar($objectExpr)) {
                        $this->fatalError($arg, 'Undefined variable `$' . $objectExpr . '`');
                    }
                }
            } elseif ($this->isArrayDimFetch($arg->value) and $this->isVarExpr($arg->value->var)) {
                $array = $this->parseIdentifier($arg->value->var);
                if ($array === 'GLOBALS') {
                    $globalVar = $this->parseGlobalsArrayDimFetch($arg->value);
                    // 全局变量作为引用参数
                    if ($byRef) {
                        $ref = $this->addTmpVar(Type::REF);
                        $this->context->beforeStmtLines[] = $ref . ' = ' . $globalVar . '.toReference();';
                        $this->addPositionalCallArg('&' . $ref, $arrayArgsVar, $list_args);
                    } else {
                        $this->addPositionalCallArg($globalVar, $arrayArgsVar, $list_args);
                    }
                    continue;
                }
                if ($this->isVarExpr($arg->value->var) and !$this->hasVar($array)) {
                    $this->fatalError($arg, 'Undefined variable `$' . $array . '`');
                }
                if ($byRef) {
                    if ($arg->value->dim === null) {
                        $this->fatalError($arg, 'Array dimension must be a constant expression');
                    }
                    $this->addPositionalCallArg($array . '.itemRef(' . $this->identifierToStr($arg->value->dim) . ')', $arrayArgsVar, $list_args);
                    continue;
                }
            } elseif ($this->isReferenceWrapperCall($arg->value)) {
                $inner = $this->unwrapReferenceWrapperCall($arg->value, $arg);
                if ($this->isVarExpr($inner)) {
                    $name = $this->parseVariable($inner);
                    $arg->value = $inner;
                    $this->addPositionalCallArg($this->parseArgRefVar($arg, $name), $arrayArgsVar, $list_args);
                    continue;
                }
                $expr = $this->expandRefvalExpr($inner, $arg);
                if ($expr !== null) {
                    $this->addPositionalCallArg($expr, $arrayArgsVar, $list_args);
                    continue;
                }
                $this->fatalError($arg, 'The refval function only accepts a variable, array element, or object property');
            } else {
                if ($byRef) {
                    if ($this->isScalar($arg->value)) {
                        $this->fatalError($arg, 'The constants cannot be used as an argument for a reference-type parameter');
                    }
                    $tmpRef = $this->genTmpVarName();
                    $this->addLocalVar($tmpRef, Type::REF);
                    $this->context->beforeStmtLines[] = $tmpRef . ' = ' . $this->parseChainedExpr($arg->value, self::OP_REFVAL) . ';';
                    $this->addPositionalCallArg('&' . $tmpRef, $arrayArgsVar, $list_args);
                    continue;
                }
            }
            $value = $this->parseCallArgValue($arg);
            $this->addPositionalCallArg($value, $arrayArgsVar, $list_args);
        }

        if ($argsVar !== null) {
            return $namedArgsVar !== null ? $argsVar . ', ' . $namedArgsVar . '.array()' : $argsVar;
        }
        if ($arrayArgsVar !== null) {
            return $namedArgsVar !== null ? $arrayArgsVar . ', ' . $namedArgsVar . '.array()' : $arrayArgsVar;
        }
        $callArgs = Symbol::argList() . '{' . implode(', ', $list_args) . '}';
        return $namedArgsVar !== null ? $callArgs . ', ' . $namedArgsVar . '.array()' : $callArgs;
    }

    private function isExistingReferenceCallArg(Node\Arg $arg): bool
    {
        if (!$this->isVarExpr($arg->value)) {
            return $this->isReferenceWrapperCall($arg->value);
        }
        $name = $this->parseIdentifier($arg->value);
        return $this->hasVar($name) && $this->getVarType($name) === Type::REF;
    }

    protected function wrapScopedCallbackArg(Node\Arg $arg, string $value): string
    {
        $mode = $arg->getAttribute(self::ATTR_SCOPED_CALLBACK);
        if ($mode === null || !$this->methodDef) {
            return $value;
        }

        $helper = match ($mode) {
            'normalize' => 'normalizeCallableClass',
            default => 'prepareScopedCallback',
        };
        return 'php::' . $helper . '(' . $value . ', ' . $this->getCallableScopeExpr() . ')';
    }

    protected function ensureCallArgs(?string &$argsVar, array &$listArgs): string
    {
        if ($argsVar === null) {
            $argsVar = $this->genTmpVarName();
            $this->context->beforeStmtLines[] = Type::ARGS . ' ' . $argsVar . '{' . Symbol::argList() . '{' . implode(', ', $listArgs) . '}};';
            $listArgs = [];
        }
        return $argsVar;
    }

    protected function ensureCallArrayArgs(?string &$arrayArgsVar, array &$listArgs): string
    {
        if ($arrayArgsVar === null) {
            $arrayArgsVar = $this->genTmpVarName();
            $this->context->beforeStmtLines[] = Type::ARRAY . ' ' . $arrayArgsVar . '{' . implode(', ', $listArgs) . '};';
            $listArgs = [];
        }
        return $arrayArgsVar;
    }

    protected function ensureCallNamedArgs(?string &$namedArgsVar): string
    {
        if ($namedArgsVar === null) {
            $namedArgsVar = $this->genTmpVarName();
            $this->context->beforeStmtLines[] = Type::ARRAY . ' ' . $namedArgsVar . ';';
            $this->context->afterStmtLines[] = $namedArgsVar . '.unset();';
        }
        return $namedArgsVar;
    }

    protected function addPositionalCallArg(string $value, ?string $arrayArgsVar, array &$listArgs): void
    {
        if ($arrayArgsVar !== null) {
            $this->context->beforeStmtLines[] = $arrayArgsVar . '.append(' . $value . ');';
        } else {
            $listArgs[] = $value;
        }
    }

    protected function parseCallArgValue(Node\Arg $arg): string
    {
        $this->assertExprCanBeUsedAsValue($arg->value, 'function argument');
        if ($this->isVarExpr($arg->value)) {
            $this->assertStdContainerDoesNotEscapeNativeObjects(
                $arg,
                $this->parseIdentifier($arg->value),
            );
        }
        $class = $this->detectClassOfExpr($arg->value);
        if ($class !== '' && $this->isNativeObjectClass($class)) {
            $this->fatalError(
                $arg,
                'Native objects cannot cross a dynamic PHP/ZendVM call boundary'
            );
        }
        // C++17 evaluates php::ArgList{...} elements from left to right, but a
        // later argument may emit captured beforeStmtLines while being lowered.
        // Those statements are placed before the whole outer call and would
        // overtake an earlier Call left inside the initializer list. Complete
        // each direct Call in a temporary before lowering the next argument.
        $expr = $arg->value instanceof Expr\FuncCall
            || $arg->value instanceof Expr\MethodCall
            || $arg->value instanceof Expr\StaticCall
            ? $this->parseOrderedArg($arg)
            : $this->parseArg($arg);
        return $this->materializeCallArgValue($arg->value, $expr);
    }

    protected function materializeCallArgValue(NodeAbstract $value, string $expr): string
    {
        // A Native property fetch is a typed C++ pointer, never an INDIRECT
        // zval. Passing it through php::deindirect() would box the pointer as a
        // bool/Variant and break the Native ABI. Dynamic Zend calls reject the
        // value before reaching here; direct Native calls keep it unchanged.
        if ($this->isNativeObjectClass($this->detectClassOfExpr($value))) {
            return $expr;
        }
        // A call that returns by reference yields a live php::Ref aliasing the
        // callee's storage. When such a call feeds a by-value argument, PHP takes
        // a value snapshot at evaluation time (left to right), so later mutations
        // to the aliased storage must not be observable. The dynamic ArgList keeps
        // references verbatim (Ctor::CopyRef), so we dereference into a temporary
        // value at the point of the call.
        $expr = $this->materializeRefReturnAsValue($value, $expr);
        if (!$this->shouldMaterializeCallArg($value)) {
            return $expr;
        }
        return 'php::deindirect(' . $expr . ')';
    }

    protected function shouldMaterializeCallArg(NodeAbstract $value): bool
    {
        if ($value instanceof Expr\ArrayDimFetch) {
            return !$this->isStdContainerExpr($value);
        }

        return $value instanceof Expr\PropertyFetch;
    }

    protected function parseReferenceCallArgValue(Node\Arg $arg): string
    {
        if ($this->isReferenceWrapperCall($arg->value)) {
            $arg->value = $this->unwrapReferenceWrapperCall($arg->value, $arg);
        }

        $this->assertNativeObjectReferenceForbidden($arg->value, $arg);

        if ($this->isVarExpr($arg->value)) {
            return $this->parseArgRefVar($arg, $this->parseIdentifier($arg->value));
        }

        if ($this->isPropertyFetch($arg->value)) {
            return $this->emitDynamicPropertyFetchRef($arg->value, $arg);
        }

        if ($this->isArrayDimFetch($arg->value) and $this->isVarExpr($arg->value->var)) {
            $array = $this->parseIdentifier($arg->value->var);
            if ($array === 'GLOBALS') {
                $globalVar = $this->parseGlobalsArrayDimFetch($arg->value);
                $ref = $this->addTmpVar(Type::REF);
                $this->context->beforeStmtLines[] = $ref . ' = ' . $globalVar . '.toReference();';
                return '&' . $ref;
            }
            if (!$this->hasVar($array)) {
                $this->fatalError($arg, 'Undefined variable `$' . $array . '`');
            }
            if ($arg->value->dim === null) {
                $this->fatalError($arg, 'Array dimension must be a constant expression');
            }
            return $array . '.itemRef(' . $this->identifierToStr($arg->value->dim) . ')';
        }

        if ($this->isScalar($arg->value)) {
            $this->fatalError($arg, 'The constants cannot be used as an argument for a reference-type parameter');
        }

        $tmpRef = $this->genTmpVarName();
        $this->addLocalVar($tmpRef, Type::REF);
        $this->context->beforeStmtLines[] = $tmpRef . ' = ' . $this->parseChainedExpr($arg->value, self::OP_REFVAL) . ';';
        return '&' . $tmpRef;
    }

    protected function isToRefCall(NodeAbstract $expr): bool
    {
        return $this->isMethodCall($expr)
            && $this->isNamedMethod($expr->name)
            && $expr->name->toString() === 'toRef';
    }

    protected function isReferenceWrapperCall(NodeAbstract $expr): bool
    {
        return $this->isRefvalCall($expr) || $this->isToRefCall($expr);
    }

    protected function unwrapReferenceWrapperCall(NodeAbstract $expr, NodeAbstract $errorNode): NodeAbstract
    {
        if ($this->isRefvalCall($expr)) {
            if (count($expr->args) !== 1) {
                $this->fatalError($errorNode, 'The refval function only accepts one parameter');
            }
            return $expr->args[0]->value;
        }

        if ($this->isToRefCall($expr)) {
            if (!empty($expr->args)) {
                $this->fatalError($errorNode, 'The toRef method does not accept parameters');
            }
            return $expr->var;
        }

        $this->fatalError($errorNode, 'Expected a reference wrapper call');
    }

    /**
     * 展开 refval() 调用中的数组元素或对象属性，返回对应的 C++ 引用表达式。
     * 若为普通变量则返回 null，由调用方自行处理。
     */
    protected function expandRefvalExpr(NodeAbstract $inner, Node\Arg $arg): ?string
    {
        if ($this->isPropertyFetch($inner)) {
            return $this->emitDynamicPropertyFetchRef($inner, $arg);
        }
        if ($this->isArrayDimFetch($inner) and $this->isVarExpr($inner->var)) {
            $array = $this->parseIdentifier($inner->var);
            if ($array === 'GLOBALS') {
                $globalVar = $this->parseGlobalsArrayDimFetch($inner);
                $ref = $this->addTmpVar(Type::REF);
                $this->context->beforeStmtLines[] = $ref . ' = ' . $globalVar . '.toReference();';
                return '&' . $ref;
            }
            if (!$this->hasVar($array)) {
                $this->fatalError($arg, 'Undefined variable `$' . $array . '`');
            }
            if ($inner->dim === null) {
                $this->fatalError($arg, 'Array dimension must be a constant expression');
            }
            return $array . '.itemRef(' . $this->identifierToStr($inner->dim) . ')';
        }
        return null;
    }

    /**
     * 仅用于动态调用的参数解析
     */
    protected function parseArgRefVar(Node\Arg $arg, string $name): string
    {
        if (!$this->hasVar($name)) {
            // 若参数是引用类型，可以传入未定义变量，将立即创建变量作为引用
            $this->addLocalVar($name, Type::REF);
        } elseif ($this->getVarType($name) === Type::REF) {
            return '&' . $name;
        } else {
            // 本地变量，且是原生类型，则转为普通变量
            if ($this->hasLocalVar($name) and $this->isNativeType($this->getVarType($name))) {
                $this->context->localVars[$name] = Type::VAR;
            }
            // 需要引用类型的参数，使用临时变量作为引用，并替换掉实际的参数
            $tmpVar = $this->genTmpVarName();
            $this->addLocalVar($tmpVar, Type::REF);
            $this->context->beforeStmtLines[] = $tmpVar . ' = ' . $this->parseExpr($arg->value) . '.toReference();';
            $name = $tmpVar;
        }
        // 动态调用，参数列表是 Variant 类型而不是 Reference，必须使用 & 符号取地址，传递指针，以保持引用传递
        return '&' . $name;
    }

    protected function parseArg(Node\Arg $arg): string
    {
        if ($this->isArrayDimFetch($arg->value) and $this->isStdContainerExpr($arg->value)) {
            if ($this->isStdArrayExpr($arg->value)) {
                $valueExpr = $this->parseStdArrayDimFetch($arg->value);
                $attr = $arg->value->getAttribute('stdArrayDimFetch');
                if ($attr['accessLevel'] === $attr['totalLevel']) {
                    return $this->convertExprFromType($this->context->stdArrays[$attr['var']]['type'], $valueExpr);
                } else {
                    return $this->convertArrayExpr($valueExpr);
                }
            } else {
                $valueExpr = $this->parseStdContainerDimFetch($arg->value);
                $attr = $arg->value->getAttribute('stdContainerDimFetch');
                return $this->convertExprFromType($this->context->stdContainers[$attr['var']]['type'], $valueExpr);
            }
        }
        $expr = $this->parseIdentifier($arg->value);
        if ($this->isVarExpr($arg->value) and $arg->value->name === 'GLOBALS') {
            return 'php::globalsArray()';
        }
        if ($this->isVarExpr($arg->value) and $this->isStdContainer($arg->value->name)) {
            return $this->convertArrayExpr($expr . '_ref');
        }
        return $expr;
    }

    protected function parseOrderedArg(Node\Arg $arg): string
    {
        if ($this->isArrayDimFetch($arg->value) and $this->isStdContainerExpr($arg->value)) {
            return $this->parseArg($arg);
        }
        if ($this->isVarExpr($arg->value) and $arg->value->name === 'GLOBALS') {
            return 'php::globalsArray()';
        }
        if ($this->isVarExpr($arg->value) and $this->isStdContainer($arg->value->name)) {
            return $this->convertArrayExpr($this->parseIdentifier($arg->value) . '_ref');
        }
        return $this->parseOrderedOperand($arg->value, false);
    }

    protected function parseArrayArg(Node\Arg $expr): string
    {
        $value = $expr->value;
        if ($this->isVarExpr($value)) {
            $var = $this->parseIdentifier($value);
            if (!$this->hasVar($var)) {
                $this->errorUndefinedVariable($value);
            }
            if ($this->getVarType($var) === Type::ARRAY) {
                return $var;
            }
        }
        return $this->parseExpr($value);
    }

}
