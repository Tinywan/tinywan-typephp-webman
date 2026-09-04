<?php

use CrossFileTrait\ClassB;
use CrossFileTrait\ClassC;

function callCrossFileTraitMethods(): void
{
    $withMagic = new ClassB();
    $withMagic->getAttribute('with-magic');

    $withoutMagic = new ClassC();
    $withoutMagic->getAttribute('without-magic');
}
