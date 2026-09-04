<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Entity;

/**
 * Compile-time identity of an enum case flowing through constant-expression
 * evaluation. Enum case objects have request lifetime, so a constant whose
 * value is a case cannot be folded to its backing scalar (identity would be
 * lost) nor embedded in persistent class metadata as an object; carriers of
 * this value register an IS_CONSTANT_AST the engine evaluates per request.
 */
final class EnumCaseRef
{
    public function __construct(
        public readonly string $enumClass,
        public readonly string $caseName,
    ) {
    }
}
