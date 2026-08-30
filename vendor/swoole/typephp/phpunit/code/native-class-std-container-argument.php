<?php

#[Native]
class NativeStdContainerArgumentValue {}

function consume_native_std_container(mixed $value): void {}

function native_std_container_argument(): void
{
    $vector = std::vector(NativeStdContainerArgumentValue::class);
    consume_native_std_container($vector);
}
