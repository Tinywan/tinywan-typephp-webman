<?php

trait OverridePropertyMissingTrait
{
    #[\Override]
    public string $value = 'trait';
}

class OverridePropertyTraitConsumer
{
    use OverridePropertyMissingTrait;
}
