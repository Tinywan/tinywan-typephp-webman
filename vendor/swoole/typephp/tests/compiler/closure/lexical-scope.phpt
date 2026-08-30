--TEST--
closures preserve lexical class scope and late static binding scope
--FILE--
<?php

class ClosureScopeParent
{
    private static string $secret = 'private';

    public function callbacks(): array
    {
        return [
            fn(): string => self::$secret,
            static fn(): string => self::$secret,
            static fn(): string => static::class,
        ];
    }
}

class ClosureScopeChild extends ClosureScopeParent
{
}

trait ClosureScopeTrait
{
    public function traitCallback(): Closure
    {
        return static fn(): string => self::$traitSecret;
    }
}

class ClosureScopeTraitUser
{
    use ClosureScopeTrait;

    private static string $traitSecret = 'trait-private';
}

function main(): void
{
    foreach ((new ClosureScopeChild())->callbacks() as $callback) {
        $scope = (new ReflectionFunction($callback))->getClosureScopeClass();
        echo $scope?->getName(), ':', $callback(), "\n";
    }

    $traitCallback = (new ClosureScopeTraitUser())->traitCallback();
    $traitScope = (new ReflectionFunction($traitCallback))->getClosureScopeClass();
    echo $traitScope?->getName(), ':', $traitCallback(), "\n";
}
?>
--EXPECT--
ClosureScopeParent:private
ClosureScopeParent:private
ClosureScopeParent:ClosureScopeChild
ClosureScopeTraitUser:trait-private
