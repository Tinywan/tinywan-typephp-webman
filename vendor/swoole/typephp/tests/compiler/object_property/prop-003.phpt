--TEST--
Property with nullable type
--FILE--
<?php
class Response
{
    public $phrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing', // WebDAV; RFC 2518
        103 => 'Early Hints', // RFC 8297
    ];

    static public $methods = ['GET', 'PUT', 'POST',];

    public function __construct() {

    }

    public function getPhrase() {
        return $this->phrases[200] ?? 'unknown';
    }
}

function main() {
    $resp = new Response;
    var_dump($resp->phrases[100]);
    var_dump(Response::$methods);
}
?>
--EXPECT--
string(8) "Continue"
array(3) {
  [0]=>
  string(3) "GET"
  [1]=>
  string(3) "PUT"
  [2]=>
  string(4) "POST"
}