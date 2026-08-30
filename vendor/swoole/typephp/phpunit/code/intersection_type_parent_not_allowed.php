<?php

interface ITypeParent
{
}

class BaseIntersectionParent implements ITypeParent
{
}

class DemoParentIntersection extends BaseIntersectionParent
{
    public function run(parent&ITypeParent $value): void {}
}

function main() {}
