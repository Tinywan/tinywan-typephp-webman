--TEST--
Symfony Clock style timezone coalesce assignment and clone in constructor
--XFAIL--
Known AOT bug: DateTimeZone assigned through clone during constructor can become an uninitialized internal object.
--FILE--
<?php
class SymfonyNativeClockCase
{
    private DateTimeZone $timezone;

    public function __construct(DateTimeZone|string|null $timezone = null)
    {
        $this->timezone = is_string($timezone ??= date_default_timezone_get()) ? $this->withTimeZone($timezone)->timezone : $timezone;
    }

    public function withTimeZone(DateTimeZone|string $timezone): static
    {
        if (is_string($timezone)) {
            $timezone = new DateTimeZone($timezone);
        }

        $clone = clone $this;
        $clone->timezone = $timezone;

        return $clone;
    }

    public function name(): string
    {
        return $this->timezone->getName();
    }
}

function main(): void
{
    $previous = date_default_timezone_get();
    date_default_timezone_set('UTC');

    $clock = new SymfonyNativeClockCase();
    var_dump($clock->name());

    $tokyo = $clock->withTimeZone('Asia/Tokyo');
    var_dump($clock->name());
    var_dump($tokyo->name());

    $custom = new SymfonyNativeClockCase(new DateTimeZone('Europe/Paris'));
    var_dump($custom->name());

    date_default_timezone_set($previous);
}
?>
--EXPECT--
string(3) "UTC"
string(3) "UTC"
string(10) "Asia/Tokyo"
string(12) "Europe/Paris"
