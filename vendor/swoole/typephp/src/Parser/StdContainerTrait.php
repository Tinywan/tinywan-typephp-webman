<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Parser;

use TypePhp\Type;

use TypePhp\Generator\Symbol;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeAbstract;

trait StdContainerTrait
{
    /**
     * Resolve the Native value class of a std container factory without
     * creating container metadata. This is used before assignment lowering so
     * a global/static destination cannot accidentally outlive the temporary
     * NativeContainerRootFrame generated for function-local containers.
     */
    protected function getStdContainerFactoryNativeClass(NodeAbstract $expr): string
    {
        if (!$expr instanceof StaticCall
            || !$this->isNameExpr($expr->class)
            || !$this->isIdExpr($expr->name)
            || $this->parseIdentifier($expr->class) !== 'std'
        ) {
            return '';
        }

        $method = $expr->name->toString();
        if (!in_array($method, ['array', 'vector', 'map', 'ordered_map'], true)) {
            return '';
        }

        if ($method === 'array') {
            $factory = $expr;
            while ($factory instanceof StaticCall
                && $this->isNameExpr($factory->class)
                && $this->isIdExpr($factory->name)
                && $this->parseIdentifier($factory->class) === 'std'
                && $factory->name->toString() === 'array'
            ) {
                if (count($factory->args) !== 2) {
                    return '';
                }
                $value = $factory->args[0]->value;
                if (!$value instanceof StaticCall) {
                    $typeInfo = $this->parseStdValueTypeInfo($value, 'std::array');
                    $class = $typeInfo['class'] ?? '';
                    return is_string($class) && $this->isNativeObjectClass($class) ? $class : '';
                }
                $factory = $value;
            }
            return '';
        }

        $valueIndex = $method === 'vector' ? 0 : 1;
        if (!isset($expr->args[$valueIndex])) {
            return '';
        }
        $typeInfo = $this->parseStdValueTypeInfo(
            $expr->args[$valueIndex]->value,
            'std::' . $method,
        );
        $class = $typeInfo['class'] ?? '';
        return is_string($class) && $this->isNativeObjectClass($class) ? $class : '';
    }

    protected function assertNativeStdContainerFunctionLocal(NodeAbstract $expr): void
    {
        if ($this->getStdContainerFactoryNativeClass($expr) !== '') {
            $this->fatalError(
                $expr,
                'Std containers holding Native objects must be function-local',
            );
        }
    }

    protected function isStdContainerIterating(string $var): bool
    {
        return !empty($this->context->stdContainers[$var]['iterationDepth']);
    }

    protected function assertStdContainerStructureMutable(NodeAbstract $node, string $var): void
    {
        if ($this->isStdContainerIterating($var)) {
            $this->fatalError($node, "Cannot structurally modify std container `\${$var}` during foreach");
        }
    }

    protected function isStdContainer(string $var): bool
    {
        return $this->hasLocalVar($var) and $this->isStdContainerType($this->getVarType($var));
    }

    protected function isStdContainerType(string $type): bool
    {
        return in_array($type, [
            Type::STD_ARRAY,
            Type::STD_VECTOR,
            Type::STD_MAP,
            Type::STD_ORDERED_MAP,
        ], true);
    }

    protected function isStdArray(string $var): bool
    {
        return $this->hasLocalVar($var) and $this->getVarType($var) === Type::STD_ARRAY;
    }

    protected function isStdVector(string $var): bool
    {
        return $this->hasLocalVar($var) and $this->getVarType($var) === Type::STD_VECTOR;
    }

    protected function isStdMap(string $var): bool
    {
        return $this->hasLocalVar($var) and $this->getVarType($var) === Type::STD_MAP;
    }

    protected function isStdOrderedMap(string $var): bool
    {
        return $this->hasLocalVar($var) and $this->getVarType($var) === Type::STD_ORDERED_MAP;
    }

    protected function getStdTypeKey(array $info): string
    {
        $parts = [
            'kind=' . $info['kind'],
            'decl=' . $info['decl'],
            'type=' . $info['type'],
            'class=' . ($info['class'] ?? ''),
        ];
        if (isset($info['keyType'])) {
            $parts[] = 'keyType=' . $info['keyType'];
        }
        return implode(';', $parts);
    }

    protected function addStdTypeId(array $info): array
    {
        $info['typeId'] = $this->registerStdType($this->getStdTypeKey($info));
        return $info;
    }

    protected function getStdContainerVarInfo(string $var): array
    {
        if ($this->isStdArray($var)) {
            return $this->context->stdArrays[$var];
        }
        return $this->context->stdContainers[$var];
    }

    protected function getStdContainerNativeObjectClass(string $var): string
    {
        if (!$this->isStdContainer($var)) {
            return '';
        }
        $class = $this->getStdContainerVarInfo($var)['class'] ?? '';
        return is_string($class) && $this->isNativeObjectClass($class) ? $class : '';
    }

    protected function assertStdContainerDoesNotEscapeNativeObjects(
        NodeAbstract $node,
        string $var,
    ): void {
        if ($this->getStdContainerNativeObjectClass($var) !== '') {
            $this->fatalError(
                $node,
                'Std containers holding Native objects cannot cross a PHP/ZendVM value boundary',
            );
        }
    }

    protected function getStdContainerKeyType(string $var): string
    {
        if ($this->isStdVector($var) or $this->isStdArray($var)) {
            return Type::INT;
        }
        return $this->getStdContainerVarInfo($var)['keyType'];
    }

    protected function getStdContainerValueType(string $var, string $valueVar): string
    {
        $info = $this->getStdContainerVarInfo($var);
        if ($this->isStdArray($var)) {
            if (count($info['sizes']) > 1) {
                return Type::ARRAY;
            }
        }
        if ($info['type'] === Type::OBJECT && $this->isNativeObjectClass($info['class'] ?? '')) {
            $this->addNativeObject($valueVar, $info['class']);
            unset($this->context->objects[$valueVar]);
            return $this->getNativeObjectPointerType($info['class']);
        }
        if ($info['type'] === Type::OBJECT and $info['class']) {
            $this->addObject($valueVar, $info['class']);
        } else {
            unset($this->context->objects[$valueVar]);
        }
        return $info['type'];
    }

    protected function getStdArrayDecl(string $type, array $sizes, ?string $class = null): string
    {
        $decl = str_repeat(Type::STD_ARRAY . '<', count($sizes));
        $decl .= $this->getStdContainerElementType($type, $class);
        for ($i = count($sizes) - 1; $i >= 0; $i--) {
            $decl .= ', ' . $sizes[$i] . '>';
        }
        return $decl;
    }

    protected function getStdValueTypeBytes(string $type): int
    {
        return match ($type) {
            Type::BOOL => 1,
            Type::INT, Type::FLOAT => 8,
            default => 16,
        };
    }

    protected function getNestedStdArrayInfo(array $info, int $accessLevel): ?array
    {
        $sizes = array_reverse($info['sizes']);
        if ($accessLevel >= count($sizes)) {
            return null;
        }

        $nestedSizes = array_slice($sizes, $accessLevel);
        return [
            'kind' => 'array',
            'decl' => $this->getStdArrayDecl($info['type'], $nestedSizes, $info['class']),
            'type' => $info['type'],
            'class' => $info['class'],
            'sizes' => array_reverse($nestedSizes),
            'bytes' => array_product($nestedSizes) * $this->getStdValueTypeBytes($info['type']),
        ];
    }

    protected function getStdArrayDimFetchContainerInfo(Expr\ArrayDimFetch $expr): ?array
    {
        if (!$expr->hasAttribute('stdArrayDimFetch')) {
            $this->parseStdArrayDimFetch($expr);
        }
        $attr = $expr->getAttribute('stdArrayDimFetch');
        return $this->getNestedStdArrayInfo($this->context->stdArrays[$attr['var']], $attr['accessLevel']);
    }

    protected function isSameStdContainerInfo(array $leftInfo, array $rightInfo): bool
    {
        return $this->getStdTypeKey($leftInfo) === $this->getStdTypeKey($rightInfo);
    }

    protected function getStdContainerExprInfo(NodeAbstract $expr): ?array
    {
        if ($this->isVarExpr($expr)) {
            $var = $this->parseVariable($expr);
            if ($this->isStdContainer($var)) {
                return $this->getStdContainerVarInfo($var);
            }
            return null;
        }
        if ($this->isArrayDimFetch($expr) and $this->isStdArrayExpr($expr)) {
            return $this->getStdArrayDimFetchContainerInfo($expr);
        }
        return null;
    }

    protected function parseStdContainerCopyExpr(NodeAbstract $expr): string
    {
        if ($this->isVarExpr($expr)) {
            return $this->parseVariable($expr) . '_ref';
        }
        if ($this->isArrayDimFetch($expr) and $this->isStdArrayExpr($expr)) {
            return $this->parseStdArrayDimFetch($expr);
        }
        return $this->parseExpr($expr);
    }

    protected function isStdArrayExpr(Expr\ArrayDimFetch $expr): bool
    {
        $info = $this->getStdArrayInfo($expr);
        return $info !== null;
    }

    protected function isStdContainerExpr(Expr\ArrayDimFetch $expr): bool
    {
        return $this->isStdArrayExpr($expr) || $this->getStdContainerInfo($expr) !== null;
    }

    protected function getStdArrayInfo(Expr\ArrayDimFetch $expr): ?array
    {
        $tmp = $expr->var;
        while (true) {
            if ($this->isArrayDimFetch($tmp)) {
                $tmp = $tmp->var;
            } elseif ($this->isVarExpr($tmp) and $this->isStdArray($this->parseVariable($tmp))) {
                return $this->context->stdArrays[$this->parseVariable($tmp)];
            } else {
                return null;
            }
        }
    }

    protected function getStdContainerInfo(Expr\ArrayDimFetch $expr): ?array
    {
        $tmp = $expr->var;
        while (true) {
            if ($this->isArrayDimFetch($tmp)) {
                $tmp = $tmp->var;
            } elseif ($this->isVarExpr($tmp)) {
                $var = $this->parseVariable($tmp);
                if ($this->isStdVector($var) || $this->isStdMap($var) || $this->isStdOrderedMap($var)) {
                    return $this->context->stdContainers[$var];
                }
                return null;
            } else {
                return null;
            }
        }
    }

    protected function parseStdArrayAssign(NodeAbstract $left, NodeAbstract $right): string
    {
        $info = $this->getStdArrayInfo($left);
        $arrayDimFetch = $this->parseStdArrayDimFetch($left);
        $attr = $left->getAttribute('stdArrayDimFetch');
        if ($attr['accessLevel'] < $attr['totalLevel']) {
            $leftInfo = $this->getNestedStdArrayInfo($info, $attr['accessLevel']);
            $rightInfo = $this->getStdContainerExprInfo($right);
            if ($rightInfo !== null and $this->isSameStdContainerInfo($leftInfo, $rightInfo)) {
                return $arrayDimFetch . ' = ' . $this->parseStdContainerCopyExpr($right);
            }
            $this->fatalError($right, 'Cannot assign non-matching value to nested std::array');
        }
        return $arrayDimFetch . ' = ' . $this->convertStdValueExpr($info, $right);
    }

    protected function parseStdContainerAssign(Expr\ArrayDimFetch $left, NodeAbstract $right): string
    {
        if ($this->isStdArrayExpr($left)) {
            return $this->parseStdArrayAssign($left, $right);
        }

        $info = $this->getStdContainerInfo($left);
        $container = $this->parseVariable($left->var);
        if ($info['kind'] === 'vector' && $left->dim === null) {
            if (!$this->isVarExpr($left->var)) {
                $this->fatalError($left, 'std::vector append only supports a vector variable');
            }
            $vector = $this->parseVariable($left->var);
            $this->assertStdContainerStructureMutable($left, $vector);
            return $vector . '_ref.push_back(' . $this->convertStdValueExpr($info, $right) . ')';
        }
        if ($left->dim === null) {
            $this->fatalError($left, 'std map expects a key');
        }

        return $this->parseStdContainerOffsetSet($left, $this->convertStdValueExpr($info, $right));
    }

    protected function parseStdArrayAssignOp(Expr\AssignOp $expr, string $op): string
    {
        $binaryOp = $this->removeAssignOp($op);
        if ($binaryOp === '.') {
            $this->fatalError($expr, 'Cannot concat string to std::array');
        }

        $info = $this->getStdArrayInfo($expr->var);
        $arrayDimFetch = $this->parseStdArrayDimFetch($expr->var);
        $attr = $expr->var->getAttribute('stdArrayDimFetch');
        if ($attr['accessLevel'] < $attr['totalLevel']) {
            $this->fatalError($expr, 'Cannot use assign operator on nested std::array');
        }
        $rightExpr = $this->parseExpr($expr->expr);
        if (in_array($info['type'], [Type::BIGINT, Type::BIGFLOAT, Type::DECIMAL], true)) {
            $rightType = $this->detectTypeOfExpr($expr->expr);
            $item = $this->genTmpVarName();
            $bigExpr = $this->parseBigAssignOpExpr(
                $item,
                $info['type'],
                $rightExpr,
                $rightType,
                $binaryOp,
                $expr->var,
                $expr->expr
            );
            return '([&](php::Var &' . $item . ') -> php::Var & { return ' . $item . ' = ' . $bigExpr . '; })('
                . $arrayDimFetch . ')';
        }
        return $arrayDimFetch . ' ' . $binaryOp . '= ' . $this->convertExprFromType($info['type'], $rightExpr);
    }

    protected function parseStdContainerAssignOp(Expr\AssignOp $expr, string $op): string
    {
        if ($this->isStdArrayExpr($expr->var)) {
            return $this->parseStdArrayAssignOp($expr, $op);
        }

        $binaryOp = $this->removeAssignOp($op);
        if ($binaryOp === '.') {
            $this->fatalError($expr, 'Cannot concat string to std container');
        }

        $info = $this->getStdContainerInfo($expr->var);
        $containerDimFetch = $this->parseStdContainerDimFetch($expr->var, true);
        $rightExpr = $this->parseExpr($expr->expr);
        if (in_array($info['type'], [Type::BIGINT, Type::BIGFLOAT, Type::DECIMAL], true)) {
            $rightType = $this->detectTypeOfExpr($expr->expr);
            $item = $this->genTmpVarName();
            $bigExpr = $this->parseBigAssignOpExpr(
                $item,
                $info['type'],
                $rightExpr,
                $rightType,
                $binaryOp,
                $expr->var,
                $expr->expr
            );
            return '([&](php::Var &' . $item . ') -> php::Var & { return ' . $item . ' = ' . $bigExpr . '; })('
                . $containerDimFetch . ')';
        }
        return $containerDimFetch . ' ' . $binaryOp . '= ' . $this->convertExprFromType($info['type'], $rightExpr);
    }

    protected function parseStdArrayDimFetch(Expr\ArrayDimFetch $expr): string
    {
        $tmp = $expr;
        $dims = [];
        $info = $this->getStdArrayInfo($expr);

        while (true) {
            if ($this->isArrayDimFetch($tmp)) {
                if ($tmp->dim === null) {
                    $this->fatalError($tmp, 'std::array expects an index');
                }
                $dims[] = $tmp->dim;
                $tmp = $tmp->var;
            } else {
                break;
            }
        }
        if (!$this->isVarExpr($tmp)) {
            $this->fatalError($expr, 'std::array expects a variable');
        }

        $dims = array_reverse($dims);
        $sizes = array_reverse($info['sizes']);
        if (count($dims) > count($sizes)) {
            $this->fatalError($expr, 'std::array access level exceeds array dimensions');
        }

        $baseVar = $this->parseVariable($tmp);
        $nesting = [$baseVar . '_ref'];
        foreach ($dims as $level => $dim) {
            $size = $sizes[$level];
            if ($this->isScalarInt($dim)) {
                if ($dim->value < 0 || $dim->value >= $size) {
                    $this->fatalError($dim, "std::array index out of bounds: index {$dim->value}, size {$size}");
                }
            }
            $index = $this->parseExpr($dim);
            $nesting[] = '[' . Symbol::safeIndex($this->convertIntExpr($index), $size) . ']';
        }
        $expr->setAttribute('stdArrayDimFetch', ['var' => $baseVar, 'accessLevel' => count($dims), 'totalLevel' => count($sizes)]);

        return implode('', $nesting);
    }

    protected function parseForeachStdContainer(Foreach_ $node): string
    {
        $container = $this->parseIdentifier($node->expr);
        $mutableContainer = !$this->isStdArray($container);
        if ($mutableContainer) {
            $this->context->stdContainers[$container]['iterationDepth'] =
                ($this->context->stdContainers[$container]['iterationDepth'] ?? 0) + 1;
        }
        $iterator = $this->genTmpVarName();
        $code = '';
        if ($mutableContainer) {
            $guard = $this->genTmpVarName();
            $code .= '{' . PHP_EOL;
            $this->indentLevel++;
            $code .= $this->getIndent() . "auto $guard = {$container}_ref.iterationGuard();" . PHP_EOL;
            $code .= $this->getIndent();
        }
        $code .= "for (auto $iterator = {$container}_ref.begin(); $iterator != {$container}_ref.end(); ++$iterator) {" . PHP_EOL;
        $this->indentLevel++;
        if ($node->keyVar) {
            $keyVar = $this->parseIdentifier($node->keyVar);
            $this->checkVar($node, $keyVar, $this->getStdContainerKeyType($container));
            if ($this->isStdVector($container) or $this->isStdArray($container)) {
                $code .= $this->getIndent() . "$keyVar = $iterator - {$container}_ref.begin();" . PHP_EOL;
            } else {
                $code .= $this->getIndent() . "$keyVar = {$iterator}->first;" . PHP_EOL;
            }
        }

        if ($node->byRef) {
            $this->fatalError($node, 'Cannot use & with std container foreach');
        }

        if (!$this->isVarExpr($node->valueVar)) {
            $this->fatalError($node, 'Cannot assign value to std container foreach');
        }

        $valueVar = $this->parseIdentifier($node->valueVar);
        $this->checkVar($node, $valueVar, $this->getStdContainerValueType($container, $valueVar));

        if ($this->isStdVector($container) or $this->isStdArray($container)) {
            $code .= $this->getIndent() . "$valueVar = *$iterator;" . PHP_EOL;
        } else {
            $code .= $this->getIndent() . "$valueVar = {$iterator}->second;" . PHP_EOL;
        }

        try {
            $body = $this->parseForeachBody($node);
        } finally {
            if ($mutableContainer) {
                --$this->context->stdContainers[$container]['iterationDepth'];
            }
        }
        $this->indentLevel--;

        $code .= $this->parseBeforeStmtLines() . PHP_EOL;
        $code .= $body . PHP_EOL;

        $code .= $this->getIndent() . '}';
        if ($mutableContainer) {
            $this->indentLevel--;
            $code .= PHP_EOL . $this->getIndent() . '}';
        }
        unset($this->context->objects[$valueVar]);
        return $code;
    }

    protected function parseStdContainerDimFetch(Expr\ArrayDimFetch $expr, bool $forUpdate = false): string
    {
        if ($this->isStdArrayExpr($expr)) {
            return $this->parseStdArrayDimFetch($expr);
        }

        $info = $this->getStdContainerInfo($expr);
        $tmp = $expr;
        $dims = [];
        while (true) {
            if ($this->isArrayDimFetch($tmp)) {
                $dims[] = $tmp->dim;
                $tmp = $tmp->var;
            } else {
                break;
            }
        }
        if (!$this->isVarExpr($tmp)) {
            $this->fatalError($expr, 'std container expects a variable');
        }
        if (count($dims) !== 1) {
            $this->fatalError($expr, 'Nested std::vector/std::map/std::ordered_map access is not supported');
        }
        $dim = $dims[0];
        if ($dim === null) {
            $this->fatalError($expr, 'std container expects an index');
        }

        $container = $this->parseVariable($tmp);
        $index = $this->parseExpr($dim);
        $key = $info['kind'] === 'vector' ? $this->convertIntExpr($index) : $this->convertStdContainerKey($info, $index);
        $method = $forUpdate && ($info['kind'] === 'map' || $info['kind'] === 'ordered_map')
            ? 'offsetGetForUpdate'
            : 'offsetGet';
        $args = $key;
        if ($method === 'offsetGetForUpdate') {
            $defaultValue = $this->getStdContainerDefaultValueExpr($info['type']);
            if ($defaultValue !== null) {
                $method = 'offsetGetForUpdateLazy';
                $args .= ', []() { return ' . $defaultValue . '; }';
            }
        }
        $access = $container . '_ref.' . $method . '(' . $args . ')';
        $expr->setAttribute('stdContainerDimFetch', ['var' => $container, 'accessLevel' => 1, 'totalLevel' => 1]);

        return $access;
    }

    protected function parseStdContainerOffsetSet(Expr\ArrayDimFetch $expr, string $value): string
    {
        $info = $this->getStdContainerInfo($expr);
        if ($expr->dim === null) {
            $this->fatalError($expr, 'std container expects an index');
        }
        if (!$this->isVarExpr($expr->var)) {
            $this->fatalError($expr, 'std container expects a variable');
        }
        $container = $this->parseVariable($expr->var);
        $indexExpr = $this->parseExpr($expr->dim);
        $index = $info['kind'] === 'vector' ? $this->convertIntExpr($indexExpr) : $this->convertStdContainerKey($info, $indexExpr);
        return $container . '_ref.offsetSet(' . $index . ', ' . $value . ')';
    }

    protected function convertStdContainerKey(array $info, string $index): string
    {
        if ($info['keyType'] === Type::STR) {
            return $this->convertStringExpr($index);
        }
        return $this->convertIntExpr($index);
    }

    protected function getStdContainerDefaultValueExpr(string $type): ?string
    {
        return match ($type) {
            Type::BIGINT => 'php::BigInt::newInstance(0)',
            Type::BIGFLOAT => 'php::BigFloat::newInstance(0)',
            Type::DECIMAL => 'php::Decimal::newInstance(0)',
            default => null,
        };
    }

    protected function parseStdContainerOffsetUnset(Expr\ArrayDimFetch $expr): string
    {
        if ($expr->dim === null) {
            $this->fatalError($expr, 'std container expects an index');
        }

        if ($this->isStdArrayExpr($expr)) {
            $info = $this->getStdArrayInfo($expr);
            $target = $this->parseStdArrayDimFetch($expr);
            $defaultValue = $this->getStdContainerDefaultValueExpr($info['type']);
            if ($defaultValue !== null) {
                return $target . ' = ' . $defaultValue;
            }

            if ($this->isVarExpr($expr->var)) {
                $parent = $this->parseVariable($expr->var) . '_ref';
            } elseif ($this->isArrayDimFetch($expr->var)) {
                $parent = $this->parseStdArrayDimFetch($expr->var);
            } else {
                $this->fatalError($expr, 'std::array expects a variable');
            }
            $index = $this->convertIntExpr($this->parseExpr($expr->dim));
            return $parent . '.offsetUnset(' . $index . ')';
        }

        $info = $this->getStdContainerInfo($expr);
        if ($info === null || !$this->isVarExpr($expr->var)) {
            $this->fatalError($expr, 'std container expects a variable');
        }
        $container = $this->parseVariable($expr->var);
        $indexExpr = $this->parseExpr($expr->dim);
        $index = $info['kind'] === 'vector'
            ? $this->convertIntExpr($indexExpr)
            : $this->convertStdContainerKey($info, $indexExpr);
        $defaultValue = $this->getStdContainerDefaultValueExpr($info['type']);
        if ($defaultValue !== null && $info['kind'] === 'vector') {
            return $container . '_ref.offsetSet(' . $index . ', ' . $defaultValue . ')';
        }
        return $container . '_ref.offsetUnset(' . $index . ')';
    }

    protected function getStdContainerElementType(string $type, ?string $class = null): string
    {
        if ($class !== null && $this->isNativeObjectClass($class)) {
            return $this->getNativeObjectPointerType($class);
        }
        return match ($type) {
            Type::BIGINT, Type::BIGFLOAT, Type::DECIMAL, Type::STREAM, Type::BOX => Type::VAR,
            default => $type,
        };
    }

    protected function parseStdNativeType(NodeAbstract $expr, string $owner): string
    {
        if (!$this->isClassConstFetch($expr) || !$this->isNameExpr($expr->class) || !$this->isIdExpr($expr->name)
            || strcasecmp(ltrim($expr->class->toString(), '\\'), 'Type') !== 0) {
            $this->fatalError($expr, "An incorrect `{$owner}` definition");
        }
        return match ($expr->name->name) {
            'Int' => Type::INT,
            'Float' => Type::FLOAT,
            'Bool' => Type::BOOL,
            'BigInt' => Type::BIGINT,
            'BigFloat' => Type::BIGFLOAT,
            'Decimal' => Type::DECIMAL,
            default => $this->fatalError($expr, "An incorrect `{$owner}` definition"),
        };
    }

    protected function parseStdValueTypeInfo(NodeAbstract $expr, string $owner): array
    {
        if (!$this->isClassConstFetch($expr)) {
            $this->fatalError($expr, "{$owner} expects a Type constant or ClassName::class");
        }
        if (!$this->isNameExpr($expr->class) || !$this->isIdExpr($expr->name)) {
            $this->fatalError($expr, "An incorrect `{$owner}` definition");
        }
        $className = ltrim($expr->class->toString(), '\\');
        if (strcasecmp($className, 'Type') === 0) {
            return [
                'type' => match ($expr->name->name) {
                    'Int', 'Float', 'Bool', 'BigInt', 'BigFloat', 'Decimal' => $this->parseStdNativeType($expr, $owner),
                    'String' => Type::STR,
                    'Array' => Type::ARRAY,
                    'Object' => Type::OBJECT,
                    'Any' => Type::VAR,
                    'Stream' => Type::STREAM,
                    'Box' => Type::BOX,
                    default => $this->fatalError($expr, "An incorrect `{$owner}` definition"),
                },
                'class' => null,
            ];
        }
        if ($expr->name->name !== 'class') {
            $this->fatalError($expr, "{$owner} class value only supports ClassName::class");
        }
        $class = $this->parseStdClassValueType($expr, $owner);
        return ['type' => Type::OBJECT, 'class' => $class];
    }

    protected function parseStdClassValueType(Expr\ClassConstFetch $expr, string $owner): string
    {
        $class = $this->parseIdentifier($expr->class);
        if ($class === 'static') {
            $this->fatalError($expr, "{$owner} class value does not support static::class");
        }
        if ($class === 'self' || $class === 'this_') {
            if (!$this->classDef) {
                $this->fatalError($expr, "{$owner} class value cannot use self::class outside class scope");
            }
            $class = $this->getNamespacedClassName($this->class);
        } elseif ($class === 'parent') {
            if (!$this->classDef || !$this->classDef->extends) {
                $this->fatalError($expr, "{$owner} class value cannot use parent::class because current class does not extend any class");
            }
            $class = $this->getNamespacedClassName('\\' . $this->classDef->extends);
        } else {
            $class = $this->getNamespacedClassName($class);
        }
        return $class;
    }

    protected function convertStdValueExpr(array $info, NodeAbstract $expr): string
    {
        $valueExpr = $this->parseExpr($expr);
        $class = $info['class'] ?? null;
        if ($class === null) {
            $targetType = $info['type'];
            if ($targetType === Type::BIGINT || $targetType === Type::BIGFLOAT || $targetType === Type::DECIMAL || $targetType === Type::STREAM || $targetType === Type::BOX) {
                return $this->convertStdVarBackedExpr($targetType, $valueExpr, $expr);
            }
            return $this->convertExprFromType($targetType, $valueExpr);
        }
        if ($this->isNativeObjectClass($class)) {
            if ($this->isNull($expr)) {
                return 'nullptr';
            }
            $rightClass = $this->detectClassOfExpr($expr);
            if ($rightClass === '' || !$this->isObjectClassStaticallyAssignableTo($rightClass, $class)) {
                $actual = $rightClass === '' ? $this->detectTypeOfExpr($expr) : $rightClass;
                $this->fatalError(
                    $expr,
                    "Cannot assign value of type `{$actual}` to std container value of native class `{$class}`",
                );
            }
            return $valueExpr;
        }
        $rightClass = $this->detectClassOfExpr($expr);
        if ($rightClass !== '') {
            if (!$this->isObjectClassStaticallyAssignableTo($rightClass, $class)) {
                $this->fatalError($expr, "Cannot assign object of class `{$rightClass}` to std container value of class `{$class}`");
            }
        }

        return 'php::toObject(' . $valueExpr . ', ' . $this->getClassEntryPtr($class) . ')';
    }

    protected function convertStdVarBackedExpr(string $targetType, string $valueExpr, NodeAbstract $expr): string
    {
        $sourceType = $this->detectTypeOfExpr($expr);
        if ($sourceType === $targetType) {
            return $valueExpr;
        }
        if ($targetType === Type::STREAM || $targetType === Type::BOX) {
            return $valueExpr;
        }
        if ($targetType === Type::BIGINT) {
            return $this->convertBigIntExpr($valueExpr, $sourceType);
        }
        if ($targetType === Type::BIGFLOAT) {
            return $this->convertBigFloatExpr($valueExpr, $sourceType);
        }
        if ($targetType === Type::DECIMAL) {
            return $this->convertDecimalExpr($valueExpr, $sourceType, $expr);
        }
        return $valueExpr;
    }

    protected function parseToStdAssign(string $var, Expr\MethodCall $expr): string
    {
        $methodName = $expr->name->toString();
        $containerType = match ($methodName) {
            'toStdArray'        => 'array',
            'toStdVector'       => 'vector',
            'toStdMap'          => 'map',
            'toStdOrderedMap'   => 'ordered_map',
            default => $this->fatalError($expr, "Unknown std conversion method: {$methodName}"),
        };

        if (!$this->isVarExpr($expr->var)) {
            $this->fatalError($expr->var, "{$methodName}() must be called on a variable");
        }
        $sourceVar = $this->parseVariable($expr->var);
        if (!$this->hasVar($sourceVar)) {
            $this->fatalError($expr->var, 'Undefined variable `$' . $sourceVar . '`');
        }

        $name = new Name('std');
        $method = new Identifier($containerType);
        $fakeCall = new StaticCall($name, $method, $expr->args);

        if ($containerType === 'array') {
            $this->addLocalVar($var, Type::STD_ARRAY);
            $this->parseStdArray($var, $fakeCall);
            $this->context->stdArrays[$var]['boxExpr'] = $sourceVar;
            return '// StdContainer<' . $this->context->stdArrays[$var]['decl'] . '>(' . $sourceVar . ')';
        }

        if ($containerType === 'vector') {
            $this->addLocalVar($var, Type::STD_VECTOR);
            $this->parseStdVector($var, $fakeCall);
        } elseif ($containerType === 'map') {
            $this->addLocalVar($var, Type::STD_MAP);
            $this->parseStdMap($var, $fakeCall);
        } else {
            $this->addLocalVar($var, Type::STD_ORDERED_MAP);
            $this->parseStdOrderedMap($var, $fakeCall);
        }
        $this->context->stdContainers[$var]['boxExpr'] = $sourceVar;
        return '// StdContainer<' . $this->context->stdContainers[$var]['decl'] . '>(' . $sourceVar . ')';
    }

    protected function parseStdMapKeyType(NodeAbstract $expr, string $owner): string
    {
        if (!$this->isClassConstFetch($expr) || !$this->isNameExpr($expr->class) || !$this->isIdExpr($expr->name)) {
            $this->fatalError($expr, "{$owner} expects Type::Int or Type::String");
        }
        $className = ltrim($expr->class->toString(), '\\');
        $constName = $expr->name->name;
        if (strcasecmp($className, 'Type') === 0 && $constName === 'Int') {
            return Type::INT;
        }
        if (strcasecmp($className, 'Type') === 0 && $constName === 'String') {
            return Type::STR;
        }
        $this->fatalError($expr, "{$owner} key only supports Type::Int or Type::String");
    }

    protected function getStdMapDecl(
        string $containerType,
        string $keyType,
        string $valueType,
        ?string $class = null,
    ): string
    {
        return $containerType . '<' . $keyType . ', '
            . $this->getStdContainerElementType($valueType, $class) . '>';
    }

    protected function parseStdArray(string $var, Expr\StaticCall $expr): string
    {
        $tmp = $expr;
        $nesting = [];
        $totalBytes = 0;

        while (true) {
            if (count($tmp->args) !== 2) {
                $this->fatalError($tmp, 'std::array() expects two arguments');
            }
            if (!$this->isScalarInt($tmp->args[1]->value)) {
                $this->fatalError($tmp, 'std::array() expects second argument to be an integer');
            }
            $byte = 0;
            $size = $tmp->args[1]->value->value;
            $nesting[] = $size;
            $typeExpr = $tmp->args[0]->value;
            if ($this->isClassConstFetch($typeExpr)) {
                $typeInfo = $this->parseStdValueTypeInfo($typeExpr, 'std::array');
                $type = $typeInfo['type'];
                $byte = $this->getStdValueTypeBytes($type);
                break;
            }
            if ($this->isStaticCall($typeExpr)) {
                $tmp = $typeExpr;
                if (!$this->isNameExpr($tmp->class) || !$this->isIdExpr($tmp->name) || $tmp->class->toString() !== 'std' || $tmp->name->toString() !== 'array') {
                    $this->fatalError($tmp, 'An incorrect `std::array` definition');
                }
            } else {
                $this->fatalError($tmp, 'std::array() expects first argument to be a class constant');
            }
        }
        $totalBytes = array_product($nesting) * $byte;

        $decl = $this->getStdArrayDecl($type, $nesting, $typeInfo['class']);
        $this->context->stdArrays[$var] = $this->addStdTypeId([
            'kind' => 'array',
            'decl' => $decl,
            'type' => $type,
            'class' => $typeInfo['class'],
            'sizes' => array_reverse($nesting),
            'bytes' => $totalBytes,
        ]);
        return '// ' . $decl;
    }

    protected function parseStdVector(string $var, Expr\StaticCall $expr): string
    {
        if (count($expr->args) < 1 || count($expr->args) > 2) {
            $this->fatalError($expr, 'std::vector() expects one or two arguments');
        }
        $typeInfo = $this->parseStdValueTypeInfo($expr->args[0]->value, 'std::vector');
        $type = $typeInfo['type'];
        $size = null;
        if (count($expr->args) === 2) {
            if (!$this->isScalarInt($expr->args[1]->value)) {
                $this->fatalError($expr, 'std::vector() expects second argument to be an integer');
            }
            $size = $expr->args[1]->value->value;
        }
        $decl = Type::STD_VECTOR . '<'
            . $this->getStdContainerElementType($type, $typeInfo['class']) . '>';
        $this->context->stdContainers[$var] = $this->addStdTypeId([
            'kind' => 'vector',
            'decl' => $decl,
            'type' => $type,
            'class' => $typeInfo['class'],
            'size' => $size,
        ]);
        return '// ' . $decl;
    }

    protected function parseStdMap(string $var, Expr\StaticCall $expr): string
    {
        return $this->parseStdMapBase($var, $expr, 'std::map', Type::STD_MAP, 'map');
    }

    protected function parseStdOrderedMap(string $var, Expr\StaticCall $expr): string
    {
        return $this->parseStdMapBase($var, $expr, 'std::ordered_map', Type::STD_ORDERED_MAP, 'ordered_map');
    }

    private function parseStdMapBase(string $var, Expr\StaticCall $expr, string $funcName, string $containerType, string $kind): string
    {
        if (count($expr->args) !== 2) {
            $this->fatalError($expr, $funcName . '() expects two arguments');
        }
        $keyType = $this->parseStdMapKeyType($expr->args[0]->value, $funcName);
        $valueTypeInfo = $this->parseStdValueTypeInfo($expr->args[1]->value, $funcName);
        $valueType = $valueTypeInfo['type'];
        $decl = $this->getStdMapDecl($containerType, $keyType, $valueType, $valueTypeInfo['class']);
        $this->context->stdContainers[$var] = $this->addStdTypeId([
            'kind' => $kind,
            'decl' => $decl,
            'type' => $valueType,
            'class' => $valueTypeInfo['class'],
            'keyType' => $keyType,
        ]);
        return '// ' . $decl;
    }

    /**
     * Generate compile-time count for std containers.
     * Returns the C++ integer literal for the size, or false if not a std container.
     */
    protected function genStdContainerCount(NodeAbstract $expr): string|false
    {
        // Simple variable: $var
        if ($this->isVarExpr($expr)) {
            $var = $this->parseVariable($expr);
            if ($this->isStdArray($var)) {
                $info = $this->context->stdArrays[$var];
                $sizes = array_reverse($info['sizes']);
                return $sizes[0] . $this->getPlatform()->getIntegerLiteralSuffix();
            }
            if ($this->isStdVector($var)) {
                return $var . '_ref.size()';
            }
            if ($this->isStdContainer($var)) {
                return $var . '_ref.size()';
            }
            return false;
        }

        // ArrayDimFetch: $array[idx1][idx2]...
        if ($this->isArrayDimFetch($expr)) {
            $tmp = $expr;
            $dimLevel = 0;
            while ($this->isArrayDimFetch($tmp)) {
                $dimLevel++;
                $tmp = $tmp->var;
            }
            if ($this->isVarExpr($tmp)) {
                $var = $this->parseVariable($tmp);
                if ($this->isStdArray($var)) {
                    $info = $this->context->stdArrays[$var];
                    $outerSizes = array_reverse($info['sizes']);
                    if ($dimLevel < count($outerSizes)) {
                        return $outerSizes[$dimLevel] . $this->getPlatform()->getIntegerLiteralSuffix();
                    }
                }
            }

        }

        return false;
    }
}
