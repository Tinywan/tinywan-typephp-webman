--TEST--
Namespace with constants defined via const keyword
--FILE--
<?php
namespace Config\App {
    const APP_NAME = "MyApp";
    const VERSION = "2.0.0";
    const DEBUG = true;

    function getConfig(): array {
        return [
            "name" => APP_NAME,
            "version" => VERSION,
            "debug" => DEBUG,
        ];
    }
}

namespace {
    use function Config\App\getConfig;

    function main() {
        var_dump(\Config\App\APP_NAME);
        var_dump(\Config\App\VERSION);
        var_dump(\Config\App\DEBUG);
        var_dump(getConfig());
        echo "done\n";
    }
}
?>
--EXPECT--
string(5) "MyApp"
string(5) "2.0.0"
bool(true)
array(3) {
  ["name"]=>
  string(5) "MyApp"
  ["version"]=>
  string(5) "2.0.0"
  ["debug"]=>
  bool(true)
}
done
