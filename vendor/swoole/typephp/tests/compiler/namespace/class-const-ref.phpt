--TEST--
Class constant references across namespaces
--FILE--
<?php
namespace Core\Status {
    class HttpStatus {
        public const OK = 200;
        public const NOT_FOUND = 404;
        public const SERVER_ERROR = 500;
    }

    class AppConfig {
        public const MAX_CONNECTIONS = 100;
        public const TIMEOUT = 30;
    }
}

namespace Services {
    use Core\Status\HttpStatus;
    use Core\Status\AppConfig;

    class ApiClient {
        public static function check(int $code): string {
            switch ($code) {
                case HttpStatus::OK:
                    return "success";
                case HttpStatus::NOT_FOUND:
                    return "not found";
                case HttpStatus::SERVER_ERROR:
                    return "error";
                default:
                    return "unknown";
            }
        }

        public static function getMaxConnections(): int {
            return AppConfig::MAX_CONNECTIONS;
        }
    }
}

namespace {
    use Services\ApiClient;

    function main() {
        var_dump(ApiClient::check(200));
        var_dump(ApiClient::check(404));
        var_dump(ApiClient::check(500));
        var_dump(ApiClient::getMaxConnections());
        var_dump(\Core\Status\AppConfig::TIMEOUT);
        echo "done\n";
    }
}
?>
--EXPECT--
string(7) "success"
string(9) "not found"
string(5) "error"
int(100)
int(30)
done
