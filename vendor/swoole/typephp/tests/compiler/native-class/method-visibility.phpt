--TEST--
Native class: method visibility is resolved at compile time across inheritance
--FILE--
<?php

#[Native]
class NativeVisibilityBase
{
    private function baseLabel(): string
    {
        return 'base-private';
    }

    protected function protectedLabel(): string
    {
        return 'base-protected';
    }

    public function callBasePrivate(): string
    {
        return $this->baseLabel();
    }
}

#[Native]
class NativeVisibilityChild extends NativeVisibilityBase
{
    private function childLabel(): string
    {
        return 'child-private';
    }

    public function callChildPrivate(): string
    {
        return $this->childLabel();
    }

    public function callProtected(): string
    {
        return $this->protectedLabel();
    }
}

function main(): void
{
    $value = new NativeVisibilityChild();
    echo $value->callBasePrivate(), PHP_EOL;
    echo $value->callChildPrivate(), PHP_EOL;
    echo $value->callProtected(), PHP_EOL;
}

?>
--EXPECT--
base-private
child-private
base-protected
