<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Parser;

use TypePhp\Type;

use TypePhp\Resolver\Reflection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeAbstract;

trait TypeDetectionTrait
{
    public function isTypedObject(string $object): bool
    {
        return isset($this->context->objects[$object]) || isset($this->context->stableObjects[$object]);
    }

    protected function isSuperGlobal(string $var): bool
    {
        if (isset($this->superGlobalVars[$var])) {
            return true;
        }
        return false;
    }

    protected function isBigIntLiteral(Node\Scalar $expr): bool
    {
        $rawValue = $expr->getAttribute('rawValue');
        if ($rawValue === null) {
            return false;
        }
        $clean = $this->stripNumericUnderscores($rawValue);
        // Must look like a decimal integer (no dot, no hex/oct/bin prefix, all digits)
        if (!preg_match('/^\d+$/', $clean)) {
            return false;
        }
        // 19+ decimal digits exceed int64 range
        return strlen(ltrim($clean, '0')) >= 19;
    }

    protected function isDecimalLiteral(Node\Scalar $expr): bool
    {
        $rawValue = $expr->getAttribute('rawValue');
        if ($rawValue === null) {
            return false;
        }
        $clean = $this->stripNumericUnderscores($rawValue);
        // Must have a decimal point or exponent (not a pure integer)
        if (!preg_match('/[\.eE]/', $clean)) {
            return false;
        }
        // Count significant digits (exclude ., e, E, +, -)
        $digits = preg_replace('/[^0-9]/', '', $clean);
        return strlen(ltrim($digits, '0')) >= 16;
    }

    protected function isFloatStr(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_FLOAT) !== false;
    }

    protected function isIntStr(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_INT) !== false;
    }

    protected function isBoolStr(string $str): bool
    {
        return $str === 'true' || $str === 'false';
    }

    protected function isNativeType(string $type): bool
    {
        return in_array($type, [Type::INT, Type::FLOAT, Type::BOOL]);
    }

    protected function isNativeTypeVar(string $var): bool
    {
        return $this->isNativeType($this->getVarType($var));
    }

    protected function isInternalFunction(string $name): bool
    {
        $name = ltrim($name, '\\');

        return array_key_exists($name, $this->internalFunctions);
    }

    protected function isInternalClass(string $name): bool
    {
        return Reflection::isInternalClass($name);
    }

    protected function isNativeClass(string $name): bool
    {
        return $this->hasClass($name);
    }

    protected function isInterface(string $name): bool
    {
        return $this->hasInterface($name) or $this->isInternalInterface($name);
    }

    protected function isAbstractClass(string $name): bool
    {
        if ($this->isInternalClass($name)) {
            return Reflection::isAbstractClass($name);
        }
        if ($this->hasClass($name)) {
            $classDef = $this->getClass($name);
            return $classDef->isAbstract();
        }
        return false;
    }

    protected function isInternalInterface(string $name): bool
    {
        return Reflection::isInternalInterface($name);
    }

    protected function isInternalConstant(string $name): bool
    {
        return array_key_exists($name, $this->internalConstants);
    }

    protected function isAssignOpConcat(string $op): bool
    {
        return $op === '.=';
    }

    protected function isAssignOpPow(string $op): bool
    {
        return $op === '**=';
    }

    protected function isArrayVar($var): bool
    {
        return $this->isVarExpr($var) and $this->hasVar($var->name) and $this->getVarType($var->name) === Type::ARRAY;
    }

}
