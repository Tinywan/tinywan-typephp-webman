<?php
/**
 * This file is part of TypePHP.
 *
 * Maintains constant symbols and namespace use/group-use declarations.
 */

namespace TypePhp\Resolver;

use TypePhp\Type;

use PhpParser\Node;

trait DeclarationSymbolTrait
{
    protected function parseConstDef(mixed $v2): void
    {
        foreach ($v2->consts as $const) {
            $name  = $this->parseIdentifier($const->name);
            $value = $this->compilerPhase === self::PHASE_CONVERT
                ? $this->parseIdentifier($const->value)
                : '';
            if ($this->namespace) {
                $name = $this->namespace . '\\' . $name;
            }
            $this->addConstant($name, $value, $const->value);
        }
    }

    protected function addConstant(string $name, string $value, ?Node\Expr $valueExpr = null): void
    {
        $constInfo                    = new \stdClass();
        $constInfo->value             = $value;
        $constInfo->valueExpr         = $valueExpr;
        $constInfo->type = $this->compilerPhase === self::PHASE_CONVERT
            ? $this->detectStrValueType($value)
            : Type::VAR;
        $constInfo->codegenFinalized = $this->compilerPhase === self::PHASE_CONVERT;
        $constInfo->namespace = $this->namespace;
        $constInfo->name = $name;
        $this->constants[$this->escapeConstVar($name)] = $constInfo;
    }

    protected function hasConstant(string $name): bool
    {
        return isset($this->constants[$this->escapeConstVar($name)]);
    }

    protected function getConstant(string $name): string
    {
        return $this->escapeConstVar($name);
    }

    protected function getConstantType(string $name): string
    {
        return $this->constants[$this->escapeConstVar($name)]->type;
    }

    protected function detectStrValueType(mixed $constant): string
    {
        if ($this->isIntStr($constant)) {
            return Type::INT;
        }
        if ($this->isFloatStr($constant)) {
            return Type::FLOAT;
        }
        if ($this->isBoolStr($constant)) {
            return Type::BOOL;
        }

        return Type::VAR;
    }

    protected function parseUse(Node\Stmt\Use_ $v2): void
    {
        foreach ($v2->uses as $use) {
            $id = $this->parseIdentifier($use->name);
            $type = $use->type !== Node\Stmt\Use_::TYPE_UNKNOWN ? $use->type : $v2->type;
            if ($type === Node\Stmt\Use_::TYPE_FUNCTION) {
                $lastIndex = strrpos($id, '\\');
                $fn = substr($id, $lastIndex + 1);
                if ($use->alias) {
                    $this->useFunctions[$use->alias->toString()] = $id;
                } else {
                    $this->useFunctions[$fn] = $id;
                }
            } elseif ($type === Node\Stmt\Use_::TYPE_CONSTANT) {
                $lastIndex = strrpos($id, '\\');
                $cn = substr($id, $lastIndex + 1);
                $ns = substr($id, 0, $lastIndex);
                $fullName = $ns . '\\' . $cn;
                if ($use->alias) {
                    $this->useConstants[$use->alias->toString()] = $fullName;
                } else {
                    $this->useConstants[$cn] = $fullName;
                }
            } else {
                $idLower = strtolower($id);
                if ($idLower === 'native_types') {
                    $this->nativeTypes = true;
                } elseif ($idLower === 'decimal_types') {
                    $this->decimalTypes = true;
                } elseif ($idLower === 'bigint_types') {
                    $this->bigintTypes = true;
                } else {
                    if ($use->alias) {
                        // Class and namespace import aliases are case-insensitive.
                        // An explicit alias replaces the implicit short name; it
                        // must not also make the target's final segment available.
                        $this->useAliases[strtolower($use->alias->toString())] = $id;
                    } else {
                        $this->useNamespaces[] = $id;
                    }
                }
            }
        }
    }

    protected function parseGroupUse(Node\Stmt\GroupUse $node): void
    {
        $prefix = $node->prefix;
        $uses = [];
        foreach ($node->uses as $use) {
            $fullName = Node\Name::concat($prefix, $use->name);
            $uses[] = new Node\UseItem($fullName, $use->alias, $use->type);
        }
        $syntheticUse = new Node\Stmt\Use_($uses, $node->type);
        $this->parseUse($syntheticUse);
    }

}
