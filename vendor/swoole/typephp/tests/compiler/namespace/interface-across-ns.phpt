--TEST--
Namespace with interface and implementation across namespaces
--FILE--
<?php
namespace Contracts\Payment {
    interface PaymentProcessor {
        public function pay(float $amount): bool;
        public function refund(float $amount): bool;
    }
}

namespace Services\Payment {
    use Contracts\Payment\PaymentProcessor;

    class StripeProcessor implements PaymentProcessor {
        private array $log = [];

        public function pay(float $amount): bool {
            $this->log[] = "paid:{$amount}";
            return true;
        }

        public function refund(float $amount): bool {
            $this->log[] = "refunded:{$amount}";
            return true;
        }

        public function getLog(): array {
            return $this->log;
        }
    }
}

namespace {
    use Services\Payment\StripeProcessor;
    use Contracts\Payment\PaymentProcessor;

    function main() {
        $p = new StripeProcessor();
        var_dump($p->pay(100.0));
        var_dump($p->refund(50.0));
        var_dump($p->getLog());
        var_dump($p instanceof PaymentProcessor);
        echo "done\n";
    }
}
?>
--EXPECT--
bool(true)
bool(true)
array(2) {
  [0]=>
  string(8) "paid:100"
  [1]=>
  string(11) "refunded:50"
}
bool(true)
done
