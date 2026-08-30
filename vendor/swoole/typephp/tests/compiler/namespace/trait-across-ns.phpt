--TEST--
Trait used across namespaces
--FILE--
<?php
namespace Base\Traits {
    trait Logger {
        private array $log = [];

        public function log(string $msg): void {
            $this->log[] = $msg;
        }

        public function getLog(): array {
            return $this->log;
        }
    }
}

namespace App\Services {
    use Base\Traits\Logger;

    class OrderService {
        use Logger;

        public function createOrder(int $id): string {
            $this->log("order:{$id}");
            return "ok";
        }
    }

    class UserService {
        use Logger;

        public function createUser(string $name): string {
            $this->log("user:{$name}");
            return "ok";
        }
    }
}

namespace {
    use App\Services\OrderService;
    use App\Services\UserService;

    function main() {
        $os = new OrderService();
        $us = new UserService();

        $os->createOrder(42);
        $us->createUser("alice");

        var_dump($os->getLog());
        var_dump($us->getLog());
        echo "done\n";
    }
}
?>
--EXPECT--
array(1) {
  [0]=>
  string(8) "order:42"
}
array(1) {
  [0]=>
  string(10) "user:alice"
}
done
