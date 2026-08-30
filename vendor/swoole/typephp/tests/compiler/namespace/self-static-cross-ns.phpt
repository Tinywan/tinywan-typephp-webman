--TEST--
self/static resolution with inheritance across namespaces
--FILE--
<?php
namespace Base\Core {
    class BaseController {
        protected static string $prefix = "base";

        public static function whoAmI(): string {
            return static::$prefix;
        }

        public static function selfWhoAmI(): string {
            return self::$prefix;
        }
    }
}

namespace App\Http {
    use Base\Core\BaseController;

    class UserController extends BaseController {
        protected static string $prefix = "user";
    }

    class AdminController extends BaseController {
        protected static string $prefix = "admin";
    }
}

namespace {
    use App\Http\UserController;
    use App\Http\AdminController;
    use Base\Core\BaseController;

    function main() {
        // static:: should resolve to the calling class
        var_dump(UserController::whoAmI());
        var_dump(AdminController::whoAmI());
        // self:: should always resolve to BaseController
        var_dump(BaseController::selfWhoAmI());
        var_dump(UserController::selfWhoAmI());
        echo "done\n";
    }
}
?>
--EXPECT--
string(4) "user"
string(5) "admin"
string(4) "base"
string(4) "base"
done
