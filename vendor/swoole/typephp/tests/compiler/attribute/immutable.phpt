--TEST--
Immutable compile-time attribute preserves read-only methods and parameters
--FILE--
<?php

function inspectImmutable(#[Immutable] ImmutableUser $user): string
{
    $alias = $user;
    return $alias->label();
}

function sumImmutable(#[Immutable] array $values): int
{
    return count($values) + $values->count() + $values[0] + $values[1];
}

function inspectImmutableReference(#[Immutable] ImmutableUser &$user): string
{
    return $user->name();
}

trait ImmutableNameTrait
{
    #[Immutable]
    public function traitName(): string
    {
        return $this->name();
    }
}

class ImmutableUser
{
    use ImmutableNameTrait;

    private string $name = 'Rango';

    #[Immutable]
    public function name(): string
    {
        return $this->name;
    }

    #[Immutable]
    public function describe(): string
    {
        return inspectImmutable($this) . ':' . $this->name();
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }
}

class ImmutableHookedValue
{
    public string $value = 'hook' {
        #[Immutable]
        get => strtoupper($this->value);
    }

    #[Immutable]
    public function read(): string
    {
        return $this->value;
    }
}

class ImmutableReader
{
    public function __construct(#[Immutable] ImmutableUser $user)
    {
        echo $user->name(), PHP_EOL;
    }
}

#[MethodsFor(ImmutableUser::class)]
class ImmutableUserMethods
{
    public static function label(#[Immutable] ImmutableUser $user): string
    {
        return $user->name();
    }
}

function cloneImmutable(#[Immutable] ImmutableUser $user): string
{
    $copy = clone $user;
    $copy->rename('Clone');
    return $copy->name();
}

function deliberatelyEscapeImmutableCheck(#[Immutable] ImmutableUser $user): string
{
    $method = 'rename';
    $user->$method('Dynamic');
    return $user->name();
}

function closureImmutableParameter(ImmutableUser $user): string
{
    $callback = function (#[Immutable] ImmutableUser $value): string {
        return $value->name();
    };
    return $callback($user);
}

function dynamicTargetEscape(mixed $target, #[Immutable] ImmutableUser $user): void
{
    // The runtime receiver hides the parameter contract from the compiler.
    $target->accept($user);
}

function main(): void
{
    $user = new ImmutableUser();
    echo $user->describe(), PHP_EOL;
    echo $user->traitName(), PHP_EOL;
    echo sumImmutable([2, 3]), PHP_EOL;
    echo cloneImmutable($user), PHP_EOL;
    echo inspectImmutableReference($user), PHP_EOL;
    echo deliberatelyEscapeImmutableCheck($user), PHP_EOL;
    echo (new ImmutableHookedValue())->read(), PHP_EOL;
    echo closureImmutableParameter($user), PHP_EOL;
    new ImmutableReader($user);
}
?>
--EXPECT--
Rango:Rango
Rango
9
Clone
Rango
Dynamic
HOOK
Dynamic
Dynamic
