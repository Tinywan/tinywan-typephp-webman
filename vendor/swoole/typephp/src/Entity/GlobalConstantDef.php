<?php

namespace TypePhp\Entity;

use PhpParser\Node;

/** Metadata for a namespace/global constant collected during preprocessing. */
final class GlobalConstantDef
{
    public string $type;
    public bool $codegenFinalized;
    public string $initializationCode = '';
    public string $afterInitializationCode = '';

    public function __construct(
        public string $name,
        public string $value,
        public ?Node\Expr $valueExpr,
        public string $namespace,
        public string $sourceFile,
        string $type,
        bool $codegenFinalized,
    ) {
        $this->type = $type;
        $this->codegenFinalized = $codegenFinalized;
    }
}
