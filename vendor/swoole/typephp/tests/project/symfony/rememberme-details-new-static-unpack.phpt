--TEST--
Symfony style RememberMeDetails new static(...array after unset)
--FILE--
<?php
class AuthenticationException extends Exception {}

class RememberMeDetails
{
    public const COOKIE_DELIMITER = ':';

    public function __construct(
        public string $userIdentifier,
        public int $expires,
        public string $value,
    ) {
        var_dump(get_debug_type($userIdentifier), get_debug_type($expires), get_debug_type($value));
    }

    public static function fromRawCookie(string $rawCookie): self
    {
        if (!str_contains($rawCookie, self::COOKIE_DELIMITER)) {
            $rawCookie = 'prefix'.self::COOKIE_DELIMITER.$rawCookie;
        }

        $cookieParts = explode(self::COOKIE_DELIMITER, $rawCookie, 4);

        if (4 !== count($cookieParts)) {
            throw new AuthenticationException('The cookie contains invalid data.');
        }

        unset($cookieParts[0]);

        return new static(...$cookieParts);
    }
}

function main(): void
{
    $raw = 'prefix:user-name:12345:series-token';
    $details = RememberMeDetails::fromRawCookie($raw);
    var_dump($details::class);
}
?>
--EXPECT--
string(6) "string"
string(3) "int"
string(6) "string"
string(17) "RememberMeDetails"
