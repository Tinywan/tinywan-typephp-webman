--TEST--
Trait-composed methods override inherited methods in virtual dispatch
--FILE--
<?php

class DispatchBase
{
    public function dispatch(): string
    {
        return $this->perform();
    }

    protected function perform(): string
    {
        return 'base';
    }
}

trait DirectOverride
{
    protected function perform(): string
    {
        return 'direct-trait';
    }
}

final class DirectConsumer extends DispatchBase
{
    use DirectOverride;
}

trait NestedOverride
{
    protected function perform(): string
    {
        return 'nested-trait';
    }
}

trait NestedComposition
{
    use NestedOverride;
}

final class NestedConsumer extends DispatchBase
{
    use NestedComposition;
}

trait AliasedOverride
{
    protected function replacement(): string
    {
        return 'aliased-trait';
    }
}

final class AliasedConsumer extends DispatchBase
{
    use AliasedOverride {
        replacement as perform;
    }
}

class PrivateDispatchBase
{
    public function dispatch(): string
    {
        return $this->perform();
    }

    private function perform(): string
    {
        return 'private-base';
    }
}

trait PrivateNameCollision
{
    protected function perform(): string
    {
        return 'trait';
    }
}

final class PrivateConsumer extends PrivateDispatchBase
{
    use PrivateNameCollision;
}

function main(): void
{
    var_dump((new DirectConsumer())->dispatch());
    var_dump((new NestedConsumer())->dispatch());
    var_dump((new AliasedConsumer())->dispatch());
    var_dump((new PrivateConsumer())->dispatch());
}
?>
--EXPECT--
string(12) "direct-trait"
string(12) "nested-trait"
string(13) "aliased-trait"
string(12) "private-base"
