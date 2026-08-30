<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Generator;

use TypePhp\Entity\ArgInfo;

trait PropertyPromotion
{
    protected function genPropertyPromotion(ArgInfo $argInfo): string
    {
        $code = '';
        $propertyName = $argInfo->phpName ?: $this->unescapeVarName($argInfo->name);
        if ($this->classDef?->nativeObject) {
            $value = $this->getNativeObjectArgumentType($argInfo) !== null
                ? $argInfo->name
                : $this->convertExprFromType($argInfo->type, $argInfo->name);
            $property = $this->classDef->getProperty($propertyName);
            return 'this_.' . $this->getNativeObjectPropertyCppName($property, $this->classDef)
                . ' = ' . $value . ';' . PHP_EOL;
        }
        $code .= 'this_.setProperty(' . $this->genCharPtr($propertyName) . ', ' . $argInfo->name . ')';
        $code .= ";\n";
        return $code;
    }
}
