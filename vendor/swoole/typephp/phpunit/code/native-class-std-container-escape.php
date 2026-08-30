<?php

#[Native]
class NativeStdContainerEscapeValue {}

function native_std_container_escape(): void
{
    $vector = std::vector(NativeStdContainerEscapeValue::class);
    $vector[] = new NativeStdContainerEscapeValue();
    $phpArray = $vector;
}
