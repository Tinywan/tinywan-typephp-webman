<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class NotNullLowering
{
    public static function createCheck(string $name): Stmt\If_
    {
        return new Stmt\If_(new Expr\BinaryOp\Identical(
            new Expr\Variable($name),
            new Expr\ConstFetch(new Node\Name('null')),
        ), [
            'stmts' => [new Stmt\Expression(new Expr\Throw_(new Expr\New_(
                new Node\Name\FullyQualified('ValueError'),
                [new Node\Arg(new Node\Scalar\String_('Parameter $' . $name . ' must not be null'))],
            )))],
        ]);
    }
}
