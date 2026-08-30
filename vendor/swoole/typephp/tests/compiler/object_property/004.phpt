--TEST--
default array property
--FILE--
<?php
class Test {
    protected string $x = '[{"label":"Option 1","value":"1"},{"label":"Option 2","value":"2"},{"label":"Option 3","value":"3"},{"label":"Option 4","value":"4"}]';

    public function bar() {
        $json = json_decode($this->x,true);
        Assert::isArray($json);
        Assert::count($json, 4);
        Assert::eq($json[0]['label'], "Option 1");
        echo "DONE\n";
    }
}

function main() {
    require __DIR__ . '/../../../src/Assert.php';
    $obj = new Test;
    $obj->bar();
}
?>
--EXPECT--
DONE