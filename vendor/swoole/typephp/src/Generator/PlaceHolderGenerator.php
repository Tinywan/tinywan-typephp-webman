<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Generator;

trait PlaceHolderGenerator
{
    protected function genPlaceHolder(string $callable): string
    {
        if ($this->classDef) {
            return 'php::makeScopedCallable(' . $callable . ', ' . $this->getCallableScopeExpr() . ')';
        }

        $ce = $this->getClassEntryPtr(\Closure::class);
        $fn = $ce . ', ' . $this->getMethodPtr('Closure', 'fromCallable');
        return 'php::call(' . $fn . ', {' . $callable . '})';
    }
}
