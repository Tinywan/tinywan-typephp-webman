<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Entity;

class ArrayInitPlan
{
    public string $expr;
    public string $init;
    public string $clean;

    public function __construct(string $expr, string $init = '', string $clean = '')
    {
        $this->expr = $expr;
        $this->init = $init;
        $this->clean = $clean;
    }

    public function requiresRuntimeInit(): bool
    {
        return $this->init !== '' || $this->clean !== '';
    }
}
