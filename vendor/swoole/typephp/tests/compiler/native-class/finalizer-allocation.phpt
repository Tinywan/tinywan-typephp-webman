--TEST--
Native class: objects allocated by a finalizer survive the active sweep and are finalized later
--FILE--
<?php

#[Native]
class NativeFinalizerNewborn
{
    public function __destruct()
    {
        global $newbornFinalized;
        $newbornFinalized++;
    }
}

#[Native]
class NativeFinalizerCreator
{
    public function __destruct()
    {
        global $creatorFinalized;
        $creatorFinalized++;
        $newborn = new NativeFinalizerNewborn();
    }
}

#[Native]
class NativeFinalizerFiller
{
    public int $value;
}

function main(): void
{
    global $creatorFinalized, $newbornFinalized;
    $creatorFinalized = 0;
    $newbornFinalized = 0;

    $creator = new NativeFinalizerCreator();
    $creator = null;
    for ($i = 0; $i < 800000; $i++) {
        $filler = new NativeFinalizerFiller();
    }

    var_dump($creatorFinalized === 1);
    var_dump($newbornFinalized === 1);
}

?>
--EXPECT--
bool(true)
bool(true)
