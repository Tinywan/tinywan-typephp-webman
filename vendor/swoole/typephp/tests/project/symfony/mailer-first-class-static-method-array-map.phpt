--TEST--
Symfony Mailer style self::method(...) callback in array_map
--FILE--
<?php
class Address
{
    public function __construct(private string $address, private string $name = '') {}

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

class PayloadBuilder
{
    public static function build(array $to, array $cc, array $bcc): array
    {
        return [
            'to' => array_map(self::encodeEmail(...), $to),
            'cc' => array_map(self::encodeEmail(...), $cc),
            'bcc' => array_map(self::encodeEmail(...), $bcc),
        ];
    }

    private static function encodeEmail(Address $address): array
    {
        return array_filter(['email' => $address->getAddress(), 'name' => $address->getName()]);
    }
}

function main(): void
{
    $payload = PayloadBuilder::build(
        [new Address('a@example.com', 'Alice')],
        [new Address('b@example.com')],
        []
    );
    var_dump($payload);
}
?>
--EXPECT--
array(3) {
  ["to"]=>
  array(1) {
    [0]=>
    array(2) {
      ["email"]=>
      string(13) "a@example.com"
      ["name"]=>
      string(5) "Alice"
    }
  }
  ["cc"]=>
  array(1) {
    [0]=>
    array(1) {
      ["email"]=>
      string(13) "b@example.com"
    }
  }
  ["bcc"]=>
  array(0) {
  }
}
