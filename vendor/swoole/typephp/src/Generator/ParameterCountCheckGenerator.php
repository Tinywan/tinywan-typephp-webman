<?php
declare(strict_types=1);

namespace TypePhp\Generator;

trait ParameterCountCheckGenerator
{
    /**
     * Generate the runtime check used at a Zend-to-TypePHP call boundary.
     *
     * TypePHP requires dynamic calls to obey the same argument-count rules as
     * statically resolved calls. This also keeps the generated internal-function
     * arginfo consistent with what its wrapper accepts.
     *
     * PHPX owns the Zend boundary details. In particular, a bare
     * ZEND_PARSE_PARAMETERS_START/END pair is invalid because ZPP expects every
     * declared parameter to be consumed by a Z_PARAM_* macro.
     */
    protected function genParameterCountCheck(int $requiredArgCount, int $declaredArgCount, bool $variadic): string
    {
        if ($requiredArgCount === 0 && $variadic) {
            return '';
        }

        return 'php::checkCallArgCount('
            . $requiredArgCount . ', '
            . $declaredArgCount . ', '
            . $this->escapeBool($variadic)
            . ');' . PHP_EOL;
    }
}
