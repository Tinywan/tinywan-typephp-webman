--TEST--
Uncaught AOT exception uses the PHP file containing main()
--FILE--
<?php
class MainFileLocationExample
{
    public function fail(): void
    {
        throw new RuntimeException('location-test');
    }
}

function main(): void
{
    (new MainFileLocationExample())->fail();
}
?>
--EXPECTF--
Fatal error: Uncaught RuntimeException: location-test in %smain-file-location.php:10
Stack trace:
%A
