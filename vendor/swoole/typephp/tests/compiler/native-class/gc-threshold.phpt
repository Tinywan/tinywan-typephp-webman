--TEST--
Native class: long-running allocation triggers automatic tracing collection
--FILE--
<?php

#[Native]
class NativeGcThresholdValue
{
    public function __destruct()
    {
        global $nativeGcFinalized;
        $nativeGcFinalized++;
    }
}

function main(): void
{
    global $nativeGcFinalized;
    $nativeGcFinalized = 0;
    // Allocate enough compact objects to cross the default 16 MiB threshold.
    for ($i = 0; $i < 1100000; $i++) {
        $value = new NativeGcThresholdValue();
    }
    var_dump($nativeGcFinalized > 0);
}

?>
--EXPECT--
bool(true)
