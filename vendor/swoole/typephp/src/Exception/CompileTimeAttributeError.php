<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Exception;

use PhpParser\Node;

final class CompileTimeAttributeError extends SyntaxError
{
    public function __construct(
        string $message,
        public readonly Node $target,
        public readonly ?string $attribute = null,
        public readonly ?Node $attributeSource = null,
        public readonly ?string $conflictAttribute = null,
        public readonly ?Node $conflictSource = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
