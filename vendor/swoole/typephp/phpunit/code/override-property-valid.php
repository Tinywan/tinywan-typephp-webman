<?php

class OverridePropertyValidParent
{
    public string $plain = 'parent';
    public string $promoted = 'parent';

    public string $hooked {
        get => 'parent';
        set {
        }
    }
}

class OverridePropertyValidChild extends OverridePropertyValidParent
{
    #[Override]
    public string $plain = 'child';

    #[\Override]
    public string $hooked {
        get => 'child';
    }

    public function __construct(
        #[\Override]
        public string $promoted = 'child',
    ) {
    }
}

trait OverridePropertyValidTrait
{
    #[\Override]
    public string $plain = 'parent';
}

class OverridePropertyValidTraitChild extends OverridePropertyValidParent
{
    use OverridePropertyValidTrait;
}
