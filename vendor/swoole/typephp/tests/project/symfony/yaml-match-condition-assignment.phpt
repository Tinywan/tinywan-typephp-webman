--TEST--
Symfony Yaml style match(true) condition with assignment and negation
--FILE--
<?php
function symfony_yaml_datetime_format(DateTimeInterface $value): string
{
    return $value->format(match (true) {
        !$length = strlen(rtrim($value->format('u'), '0')) => 'c',
        $length < 4 => 'Y-m-d\TH:i:s.vP',
        default => 'Y-m-d\TH:i:s.uP',
    });
}

function main(): void
{
    var_dump(symfony_yaml_datetime_format(new DateTimeImmutable('2024-01-02T03:04:05+00:00')));
    var_dump(symfony_yaml_datetime_format(new DateTimeImmutable('2024-01-02T03:04:05.120000+00:00')));
    var_dump(symfony_yaml_datetime_format(new DateTimeImmutable('2024-01-02T03:04:05.123456+00:00')));
}
?>
--EXPECT--
string(25) "2024-01-02T03:04:05+00:00"
string(29) "2024-01-02T03:04:05.120+00:00"
string(32) "2024-01-02T03:04:05.123456+00:00"
