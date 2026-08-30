--TEST--
use const for importing namespace constants
--FILE--
<?php
namespace Lib\Config {
    const HOST = "localhost";
    const PORT = 8080;
    const DEBUG = true;
}

namespace App {
    use const Lib\Config\HOST;
    use const Lib\Config\PORT;
    use const Lib\Config\DEBUG;

    function getServer(): string {
        return HOST . ":" . PORT;
    }

    function isDebug(): bool {
        return DEBUG;
    }
}

namespace {
    use function App\getServer;
    use function App\isDebug;

    function main() {
        var_dump(getServer());
        var_dump(isDebug());
        echo "done\n";
    }
}
?>
--EXPECT--
string(14) "localhost:8080"
bool(true)
done
