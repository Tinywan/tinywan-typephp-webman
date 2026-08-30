--TEST--
Property with nullable type
--FILE--
<?php
class Response
{
    public ?string $reason;

    public const PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing', // WebDAV; RFC 2518
        103 => 'Early Hints', // RFC 8297
    ];

    public function __construct() {

    }

    public function getReason(int $status): string
    {
        $reason = $this->reason ?: self::PHRASES[$status] ?? 'unknown';
        return $reason;
    }
}

function main() {
    $resp = new Response;
    var_dump($resp->getReason(102));
    var_dump($resp->getReason(200));
    $resp->reason = 'OK';
    var_dump($resp->getReason(102));
    var_dump($resp->getReason(200));
}
?>
--EXPECT--
string(10) "Processing"
string(7) "unknown"
string(2) "OK"
string(2) "OK"