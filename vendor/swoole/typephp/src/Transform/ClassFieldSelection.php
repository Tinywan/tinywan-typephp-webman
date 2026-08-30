<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use TypePhp\Exception\SyntaxError;

final class ClassFieldSelection
{
    /**
     * @return list<string>|null Null selects every public instance property.
     */
    public static function parse(Node\Attribute $attribute, string $name): ?array
    {
        if ($attribute->args === []) {
            return null;
        }
        if (count($attribute->args) !== 1) {
            throw new SyntaxError($name . ' accepts only the optional $fields argument');
        }

        $argument = $attribute->args[0];
        if ($argument->name !== null && $argument->name->toString() !== 'fields') {
            throw new SyntaxError($name . ' has an unknown argument $' . $argument->name->toString());
        }
        if ($argument->unpack || !$argument->value instanceof Expr\Array_) {
            throw new SyntaxError($name . ' $fields must be an array literal of property names');
        }

        $fields = [];
        foreach ($argument->value->items as $item) {
            if ($item === null || $item->unpack || $item->key !== null
                || !$item->value instanceof Node\Scalar\String_) {
                throw new SyntaxError($name . ' $fields must be a list of property-name strings');
            }
            $field = $item->value->value;
            if (in_array($field, $fields, true)) {
                throw new SyntaxError($name . ' field `' . $field . '` is specified more than once');
            }
            $fields[] = $field;
        }
        return $fields;
    }

    /** @return list<string> */
    public static function ownPublicProperties(Stmt\Class_ $class): array
    {
        $properties = [];
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Stmt\Property && $stmt->isPublic() && !$stmt->isStatic()) {
                foreach ($stmt->props as $property) {
                    $properties[] = $property->name->toString();
                }
            }
            if ($stmt instanceof Stmt\ClassMethod && $stmt->name->toLowerString() === '__construct') {
                foreach ($stmt->params as $param) {
                    if ($param->isPromoted() && ($param->flags & Modifiers::PUBLIC) && is_string($param->var->name)) {
                        $properties[] = $param->var->name;
                    }
                }
            }
        }
        return array_values(array_unique($properties));
    }

    /**
     * @param list<string>|null $selected
     * @param list<string> $available
     * @return list<string>
     */
    public static function resolve(?array $selected, array $available, string $name): array
    {
        $available = array_values(array_unique($available));
        if ($selected === null) {
            return $available;
        }
        foreach ($selected as $field) {
            if (!in_array($field, $available, true)) {
                throw new SyntaxError(
                    $name . ' field `' . $field . '` must be a declared instance property accessible from the class'
                );
            }
        }
        return $selected;
    }

}
