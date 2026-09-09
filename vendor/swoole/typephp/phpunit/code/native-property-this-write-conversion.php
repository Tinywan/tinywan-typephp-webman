<?php
class NativePropertyThisWriteConversionBox
{
    public int $value = 0;

    public function setValue($dynamicValue): void
    {
        $this->value = $dynamicValue;
    }
}
