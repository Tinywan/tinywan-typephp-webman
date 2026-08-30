--TEST--
Symfony pattern: coalesce assignment inside union typed constructor flow
--XFAIL--
Known AOT bug: writing a private typed property on a cloned object through a variable can use the wrong dynamic property path.
--FILE--
<?php

class SymfonyLikeClock
{
    private DateTimeZone $timezone;

    public function __construct(DateTimeZone|string|null $timezone = null)
    {
        $this->timezone = is_string($timezone ??= date_default_timezone_get())
            ? $this->withTimeZone($timezone)->timezone
            : $timezone;
    }

    public function withTimeZone(DateTimeZone|string $timezone): static
    {
        $clone = clone $this;
        $clone->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;

        return $clone;
    }

    public function name(): string
    {
        return $this->timezone->getName();
    }
}

function main(): void
{
    date_default_timezone_set('UTC');
    var_dump((new SymfonyLikeClock())->name());
    var_dump((new SymfonyLikeClock('Asia/Shanghai'))->name());
    var_dump((new SymfonyLikeClock(new DateTimeZone('Europe/Paris')))->name());
}
?>
--EXPECT--
string(3) "UTC"
string(13) "Asia/Shanghai"
string(12) "Europe/Paris"
