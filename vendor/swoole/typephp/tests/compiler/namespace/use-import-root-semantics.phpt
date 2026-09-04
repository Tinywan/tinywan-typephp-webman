--TEST--
Namespace imports are rooted while qualified name uses still expand their first alias segment
--FILE--
<?php
namespace Carbon {
    interface CarbonInterface {}

    class Carbon implements CarbonInterface {}
}

namespace App {
    use Carbon\Carbon;
    use Carbon\CarbonInterface;
    use Carbon\{Carbon as GroupedCarbon, CarbonInterface as GroupedInterface};

    function importedNames(): array {
        return [
            Carbon::class,
            CarbonInterface::class,
            GroupedCarbon::class,
            GroupedInterface::class,
            Carbon\CarbonInterface::class,
        ];
    }
}

namespace {
    function main(): void {
        foreach (App\importedNames() as $name) {
            echo $name, "\n";
        }
    }
}
?>
--EXPECT--
Carbon\Carbon
Carbon\CarbonInterface
Carbon\Carbon
Carbon\CarbonInterface
Carbon\Carbon\CarbonInterface
