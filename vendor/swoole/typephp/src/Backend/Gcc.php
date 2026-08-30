<?php

namespace TypePhp\Backend;

use TypePhp\Platform\PlatformBase;

/**
 * GCC 编译器后端实现
 */
class Gcc extends GccLikeBackend
{
    public function __construct(PlatformBase $platform, string $compilerCommand = 'g++', ?string $linkerCommand = null)
    {
        parent::__construct($platform, $compilerCommand, $linkerCommand);
    }

    public function getName(): string
    {
        return 'GCC';
    }

    public function getLinkerCommand(): string
    {
        return $this->linkerCommand ?? $this->compilerCommand;
    }
}
