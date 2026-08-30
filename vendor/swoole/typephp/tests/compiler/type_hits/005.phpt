--TEST--
type hits
--FILE--
<?php
namespace Symfony\Component\Mailer\Transport {
    interface TransportInterface
    {
        public function send(string $message, ?string $envelope = null);
    }
}

namespace Symfony\Component\Mailer\Transport\Smtp {
    use Symfony\Component\Mailer\Transport\TransportInterface;
    class EsmtpTransport implements TransportInterface {
        public function send(string $message, ?string $envelope = null)
        {
            var_dump($message, $envelope);
        }
    }
}

namespace {
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class Baz {
    function create_transport() : TransportInterface {
        return new EsmtpTransport;
    }
}

class FooB {
    function configure(EsmtpTransport $o) {
        $o->send('Hello World', 'foo@bar');
    }
}

function main()
{
    $baz = new Baz;
    $obj = $baz->create_transport();
    $foo = new FooB;
    $foo->configure($obj);
}
}
?>
--EXPECT--
string(11) "Hello World"
string(7) "foo@bar"
