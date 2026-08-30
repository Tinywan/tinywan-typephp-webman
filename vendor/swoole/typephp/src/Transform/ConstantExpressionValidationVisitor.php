<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Transform;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;
use TypePhp\Exception\SyntaxError;

/**
 * Applies the allow_dynamic values used by php-src at each declaration site.
 *
 * false: class constants, property defaults and enum cases.
 * true: attributes, parameter defaults and global constants.
 *
 * PHP 8.4+ compiles static variable initializers as regular expressions and
 * evaluates them only once, so they do not use the constant-expression path.
 */
final class ConstantExpressionValidationVisitor extends NodeVisitorAbstract
{
    private readonly ConstantExpressionValidator $validator;
    private readonly bool $php85;

    /** @param null|Closure(Node, string): never $fatalError */
    public function __construct(
        string $phpVersion,
        private readonly ?Closure $fatalError = null,
    )
    {
        $this->validator = new ConstantExpressionValidator($phpVersion);
        $this->php85 = version_compare($phpVersion, '8.5', '>=');
    }

    public function enterNode(Node $node): null
    {
        try {
            return $this->validateNode($node);
        } catch (SyntaxError $error) {
            if ($this->fatalError !== null) {
                ($this->fatalError)($node, $error->getMessage());
            }
            throw $error;
        }
    }

    private function validateNode(Node $node): null
    {
        if ($node instanceof Node\Attribute) {
            $this->validator->validateArguments(
                $node->args,
                allowDynamic: true,
                attributeArgumentList: true,
            );
            return null;
        }

        if ($node instanceof Node\Stmt\ClassConst) {
            foreach ($node->consts as $constant) {
                $this->rejectUnsupportedClosure(
                    $constant->value,
                    'Closures in constant declarations are not supported by TypePHP',
                );
                $this->validator->validate($constant->value, allowDynamic: false);
            }
            return null;
        }

        if ($node instanceof Node\Stmt\Property) {
            foreach ($node->props as $property) {
                if ($property->default !== null) {
                    $this->rejectUnsupportedClosure(
                        $property->default,
                        'Closures in property default values are not supported by TypePHP',
                    );
                    $this->validator->validate($property->default, allowDynamic: false);
                }
            }
            return null;
        }

        if ($node instanceof Node\Stmt\EnumCase && $node->expr !== null) {
            $this->validator->validate($node->expr, allowDynamic: false);
            return null;
        }

        if ($node instanceof Node\Param && $node->default !== null) {
            $this->rejectUnsupportedClosure(
                $node->default,
                'Closures in parameter default values are not supported by TypePHP',
            );
            $this->validator->validate($node->default, allowDynamic: true);
            return null;
        }

        if ($node instanceof Node\Stmt\Const_) {
            foreach ($node->consts as $constant) {
                $this->rejectUnsupportedClosure(
                    $constant->value,
                    'Closures in constant declarations are not supported by TypePHP',
                );
                $this->validator->validate($constant->value, allowDynamic: true);
            }
            return null;
        }

        return null;
    }

    private function rejectUnsupportedClosure(Expr $expression, string $message): void
    {
        if (!$this->php85) {
            return;
        }

        $closure = (new NodeFinder())->findFirstInstanceOf($expression, Expr\Closure::class);
        if ($closure === null) {
            return;
        }

        // TODO(PHP 8.5): Lower closures in constant-expression declaration
        // values to context-aware runtime initializer plans. Constants and
        // properties cache a Closure per request, while parameter defaults
        // create one per omitted call. Never store a request-local zval in
        // persistent MINIT class metadata.
        throw new SyntaxError($message);
    }
}
