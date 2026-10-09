<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp;

use TypePhp\Build\CompilerRuntime;

/**
 * @internal
 * @coversNothing
 */
class CompilerTest extends Translator
{
    public static function create(string $rootPath = '', ?CompilerRuntime $runtime = null): CompilerTest
    {
        $instance = new self($rootPath, $runtime);
        $instance->forTest = true;
        return $instance;
    }
}
