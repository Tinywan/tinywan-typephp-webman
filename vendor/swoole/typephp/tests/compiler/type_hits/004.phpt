--TEST--
type hits
--FILE--
<?php
interface TransportInterface
{
    public function send(string $message, ?string $envelope = null);
}

class FooA implements TransportInterface {
    public function send(string $message, ?string $envelope = null)
    {
        var_dump($message, $envelope);
    }
}

function create_transport() : TransportInterface {
    return new FooA;
}

function configure_transport(FooA $o) {
    $o->send('Hello World', 'foo@bar');
}

function main()
{
    $obj = create_transport();
    configure_transport($obj);
}
?>
--EXPECT--
string(11) "Hello World"
string(7) "foo@bar"
