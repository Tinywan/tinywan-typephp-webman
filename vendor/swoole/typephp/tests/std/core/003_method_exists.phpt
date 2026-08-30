--TEST--
method_exists() / property_exists() functions
--FILE--
<?php

class TestClass {
    public int $pubProp = 0;
    private string $privProp = "secret";

    public function pubMethod(): string { return "public"; }
    private function privMethod(): string { return "private"; }
}

function main() {
    $obj = new TestClass();

    echo "method_exists:\n";
    echo method_exists($obj, "pubMethod") ? "ok-obj-pub\n" : "fail\n";
    echo method_exists($obj, "privMethod") ? "ok-obj-priv\n" : "fail\n";
    echo method_exists($obj, "noSuchMethod") ? "fail\n" : "ok-obj-none\n";
    echo method_exists(TestClass::class, "pubMethod") ? "ok-cls-pub\n" : "fail\n";
    echo method_exists(TestClass::class, "noSuchMethod") ? "fail\n" : "ok-cls-none\n";

    echo "property_exists:\n";
    echo property_exists($obj, "pubProp") ? "ok-obj-pub\n" : "fail\n";
    echo property_exists($obj, "privProp") ? "ok-obj-priv\n" : "fail\n";
    echo property_exists($obj, "noSuchProp") ? "fail\n" : "ok-obj-none\n";
    echo property_exists(TestClass::class, "pubProp") ? "ok-cls-pub\n" : "fail\n";
    echo property_exists(TestClass::class, "privProp") ? "ok-cls-priv\n" : "fail\n";
    echo property_exists(TestClass::class, "noSuchProp") ? "fail\n" : "ok-cls-none\n";

    echo "done\n";
}
?>
--EXPECT--
method_exists:
ok-obj-pub
ok-obj-priv
ok-obj-none
ok-cls-pub
ok-cls-none
property_exists:
ok-obj-pub
ok-obj-priv
ok-obj-none
ok-cls-pub
ok-cls-priv
ok-cls-none
done
