<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\TypeSystem;

use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\NodeAbstract;

/**
 * Validates the structure of PHP compound type declarations before they are
 * lowered to TypePHP's runtime type checks.
 *
 * This deliberately has no knowledge of the declaration context. Rules for
 * self/parent/static outside a class-like scope remain in the preprocessor so
 * closures, whose scope may be supplied later by Closure::bindTo(), are not
 * incorrectly treated as ordinary global functions.
 */
trait CompoundTypeDeclarationValidationTrait
{
    /** @var array<string, true> */
    private const array PHP_INTERSECTION_FORBIDDEN_TYPES = [
        'array' => true,
        'bool' => true,
        'callable' => true,
        'false' => true,
        'float' => true,
        'int' => true,
        'iterable' => true,
        'mixed' => true,
        'never' => true,
        'null' => true,
        'object' => true,
        'parent' => true,
        'self' => true,
        'static' => true,
        'string' => true,
        'true' => true,
        'void' => true,
    ];

    protected function validateCompoundTypeDeclaration(?NodeAbstract $type): void
    {
        if ($type instanceof NullableType) {
            $name = strtolower($this->parseIdentifier($type->type));
            if ($name === 'mixed') {
                $this->fatalError($type, 'Type mixed cannot be marked as nullable since mixed already includes null');
            }
            if ($name === 'null') {
                $this->fatalError($type, 'null cannot be marked as nullable');
            }
            if ($name === 'void') {
                $this->fatalError($type, 'Void can only be used as a standalone type');
            }
            if ($name === 'never') {
                $this->fatalError($type, 'never can only be used as a standalone type');
            }
            return;
        }

        if ($type instanceof IntersectionType) {
            $this->validateIntersectionTypeDeclaration($type);
            return;
        }

        if ($type instanceof UnionType) {
            $this->validateUnionTypeDeclaration($type);
        }
    }

    /**
     * @return array{members: array<string, true>, display: string}
     */
    private function validateIntersectionTypeDeclaration(IntersectionType $type): array
    {
        $members = [];
        $display = [];
        foreach ($type->types as $member) {
            [$key, $name, $classLike] = $this->getCompoundTypeMemberIdentity($member);
            $lowerName = strtolower($name);
            if (!$classLike || isset(self::PHP_INTERSECTION_FORBIDDEN_TYPES[$lowerName])) {
                $message = in_array($lowerName, ['self', 'parent', 'static'], true)
                    ? "Type '{$lowerName}' cannot be part of an intersection type"
                    : "Type {$name} cannot be part of an intersection type";
                $this->fatalError($member, $message);
            }
            if (isset($members[$key])) {
                $this->fatalError($member, "Duplicate type {$name} is redundant");
            }
            $members[$key] = true;
            $display[] = $name;
        }

        return ['members' => $members, 'display' => implode('&', $display)];
    }

    private function validateUnionTypeDeclaration(UnionType $type): void
    {
        /** @var array<string, true> $seen */
        $seen = [];
        /** @var list<array{members: array<string, true>, display: string}> $classGroups */
        $classGroups = [];
        $hasIterable = false;
        $hasArray = false;
        $hasTraversable = false;
        $hasBool = false;
        $hasTrue = false;
        $hasFalse = false;
        $hasObject = false;
        $hasClassType = false;

        foreach ($type->types as $member) {
            if ($member instanceof IntersectionType) {
                $group = $this->validateIntersectionTypeDeclaration($member);
                $this->assertDnfGroupIsNotRedundant($member, $group, $classGroups);
                $classGroups[] = $group;
                $hasClassType = true;
                continue;
            }

            [$key, $name, $classLike] = $this->getCompoundTypeMemberIdentity($member);
            $lowerName = strtolower($name);
            if (in_array($lowerName, ['mixed', 'void', 'never'], true)) {
                $this->fatalError($member, "Type {$name} can only be used as a standalone type");
            }
            if (isset($seen[$key])) {
                $this->fatalError($member, "Duplicate type {$name} is redundant");
            }

            if ($lowerName === 'iterable') {
                if ($hasArray) {
                    $this->fatalError($member, 'Duplicate type array is redundant');
                }
                if ($hasTraversable) {
                    $this->fatalError($member, 'Duplicate type Traversable is redundant');
                }
                $hasIterable = true;
            } elseif ($lowerName === 'array') {
                if ($hasIterable) {
                    $this->fatalError($member, 'Duplicate type array is redundant');
                }
                $hasArray = true;
            } elseif ($key === 'class:traversable') {
                if ($hasIterable) {
                    $this->fatalError($member, 'Duplicate type Traversable is redundant');
                }
                $hasTraversable = true;
            }

            if ($lowerName === 'bool') {
                if ($hasTrue) {
                    $this->fatalError($member, 'Duplicate type true is redundant');
                }
                if ($hasFalse) {
                    $this->fatalError($member, 'Duplicate type false is redundant');
                }
                $hasBool = true;
            } elseif ($lowerName === 'true') {
                if ($hasBool) {
                    $this->fatalError($member, 'Duplicate type true is redundant');
                }
                if ($hasFalse) {
                    $this->fatalError($member, 'Type contains both true and false, bool must be used instead');
                }
                $hasTrue = true;
            } elseif ($lowerName === 'false') {
                if ($hasBool) {
                    $this->fatalError($member, 'Duplicate type false is redundant');
                }
                if ($hasTrue) {
                    $this->fatalError($member, 'Type contains both true and false, bool must be used instead');
                }
                $hasFalse = true;
            }

            if ($lowerName === 'object') {
                $hasObject = true;
            } elseif ($classLike) {
                $hasClassType = true;
                $group = ['members' => [$key => true], 'display' => $name];
                $this->assertDnfGroupIsNotRedundant($member, $group, $classGroups);
                $classGroups[] = $group;
            }

            $seen[$key] = true;
        }

        if ($hasObject && $hasClassType) {
            $this->fatalError($type, 'Type ' . $this->compoundTypeToString($type) . ' contains both object and a class type, which is redundant');
        }
    }

    /**
     * @param array{members: array<string, true>, display: string} $group
     * @param list<array{members: array<string, true>, display: string}> $previousGroups
     */
    private function assertDnfGroupIsNotRedundant(NodeAbstract $node, array $group, array $previousGroups): void
    {
        foreach ($previousGroups as $previous) {
            $sameMembers = count($group['members']) === count($previous['members'])
                && array_diff_key($group['members'], $previous['members']) === [];
            if ($sameMembers) {
                $this->fatalError(
                    $node,
                    "Type {$group['display']} is redundant with type {$previous['display']}",
                );
            }

            $groupContainsPrevious = array_diff_key($previous['members'], $group['members']) === [];
            $previousContainsGroup = array_diff_key($group['members'], $previous['members']) === [];
            if ($groupContainsPrevious || $previousContainsGroup) {
                $moreRestrictive = count($group['members']) > count($previous['members']) ? $group : $previous;
                $lessRestrictive = $moreRestrictive === $group ? $previous : $group;
                $this->fatalError(
                    $node,
                    "Type {$moreRestrictive['display']} is redundant as it is more restrictive than type {$lessRestrictive['display']}",
                );
            }
        }
    }

    /**
     * @return array{string, string, bool} canonical key, display name, class-like
     */
    private function getCompoundTypeMemberIdentity(NodeAbstract $member): array
    {
        $name = $this->parseIdentifier($member);
        $lowerName = strtolower($name);
        if (in_array($lowerName, ['self', 'parent', 'static'], true)) {
            return ['class:' . $lowerName, $lowerName, true];
        }
        if ($member instanceof Node\Identifier || isset($this->zendTypeMap[$lowerName])) {
            return ['builtin:' . $lowerName, $lowerName, false];
        }

        if ($member instanceof Node\Name) {
            $resolvedName = $member->getAttribute('resolvedName');
            if ($resolvedName instanceof Node\Name) {
                $name = $resolvedName->toString();
            } elseif ($member instanceof Node\Name\FullyQualified) {
                $name = $member->toString();
            } else {
                $name = $this->getNamespacedClassName($name);
            }
        }

        return ['class:' . strtolower(ltrim($name, '\\')), ltrim($name, '\\'), true];
    }

    private function compoundTypeToString(NodeAbstract $type): string
    {
        if ($type instanceof UnionType) {
            return implode('|', array_map(fn (NodeAbstract $member): string => $this->compoundTypeToString($member), $type->types));
        }
        if ($type instanceof IntersectionType) {
            return implode('&', array_map(fn (NodeAbstract $member): string => $this->compoundTypeToString($member), $type->types));
        }
        if ($type instanceof NullableType) {
            return '?' . $this->compoundTypeToString($type->type);
        }
        [, $name] = $this->getCompoundTypeMemberIdentity($type);
        return $name;
    }
}
