--TEST--
Namespace with class inheritance across namespaces
--FILE--
<?php
namespace Base\Entity {
    abstract class Model {
        protected string $table;

        public function __construct(string $table) {
            $this->table = $table;
        }

        public function getTable(): string {
            return $this->table;
        }

        abstract public function fields(): array;
    }
}

namespace App\Models {
    use Base\Entity\Model;

    class User extends Model {
        public function __construct() {
            parent::__construct("users");
        }

        public function fields(): array {
            return ["id", "name", "email"];
        }
    }

    class Product extends Model {
        public function __construct() {
            parent::__construct("products");
        }

        public function fields(): array {
            return ["id", "title", "price"];
        }
    }
}

namespace {
    use App\Models\User;
    use App\Models\Product;

    function main() {
        $user = new User();
        $product = new Product();

        var_dump($user->getTable());
        var_dump($user->fields());
        var_dump($product->getTable());
        var_dump($product->fields());
        echo "done\n";
    }
}
?>
--EXPECT--
string(5) "users"
array(3) {
  [0]=>
  string(2) "id"
  [1]=>
  string(4) "name"
  [2]=>
  string(5) "email"
}
string(8) "products"
array(3) {
  [0]=>
  string(2) "id"
  [1]=>
  string(5) "title"
  [2]=>
  string(5) "price"
}
done
