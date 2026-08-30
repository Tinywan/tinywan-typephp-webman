<?php

namespace LibraryApi;

use \Arrayable;
use \MethodsFor as Provider;
use \Constructor;
use \Validate;
use \Getter;
use \Hot;
use \Immutable;
use \NotNull;
use \NoExport as Internal;
use \Override;
use \MustUse;
use \Printer;
use \Cold;
use \Setter;
use \Type;
use \With;

#[Printer(fields: ['value', 'doubled'])]
#[Arrayable(['value'])]
class Counter
{
    public const int STEP = 2;
    #[Constructor, Getter, Setter, With]
    public int $value = 1;
    public int $doubled {
        get {
            return $this->value * 2;
        }
        set(int $value) {
            $this->value = intdiv($value, 2);
        }
    }

    public function add(int $amount = self::STEP): int
    {
        $this->value += $amount;
        return $this->value;
    }

    #[Immutable]
    public function current(): int
    {
        return $this->value;
    }

    #[MustUse, Cold]
    public function label(#[NotNull, Validate(FILTER_VALIDATE_EMAIL)] string $value): string
    {
        return $value;
    }

    #[Internal]
    public function reset(): void
    {
        $this->value = 0;
    }
}

#[Internal]
class InternalCounter
{
    public function value(): int
    {
        return 42;
    }
}

#[Internal]
#[Provider(Type::String)]
class InternalStringExtension
{
    public static function byteLength(string $value): int
    {
        return strlen($value);
    }
}

#[MustUse, Hot]
function twice(int $value): int
{
    return $value * 2;
}

function inspect(#[Immutable] Counter $counter): int
{
    return $counter->current();
}

#[Internal]
function internal_twice(int $value = 2): int
{
    return $value * 2;
}

class LibraryParent
{
    public function version(): int
    {
        return 1;
    }
}

class LibraryChild extends LibraryParent
{
    #[Override]
    public function version(): int
    {
        return 2;
    }
}
