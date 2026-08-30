<?php

class StdContainerStaticBase
{
}

class StdContainerStaticChild extends StdContainerStaticBase
{
}

function test_std_container_static_class_mismatch(): void
{
    $vector = std::vector(StdContainerStaticBase::class);
    $vector[] = new StdContainerStaticChild();
}
