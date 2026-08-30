--TEST--
SSA object prop: typed object property supports unset
--FILE--
<?php
use native_types;

class ObjPropValue {
    public function name(): string {
        return "value";
    }
}

function makeObjPropValue(): ObjPropValue {
    return new ObjPropValue();
}

class ObjPropFactory {
    public static function create(): ObjPropValue {
        return new ObjPropValue();
    }
}

class ObjPropHolder {
    public ObjPropValue $prop;

    public function run(): void {
        $this->prop = new ObjPropValue();
        var_dump(isset($this->prop));
        var_dump($this->prop->name());

        $this->prop = new ObjPropValue();
        unset($this->prop);
        var_dump(isset($this->prop));

        $this->prop = makeObjPropValue();
        var_dump($this->prop->name());

        $this->prop = ObjPropFactory::create();
        var_dump($this->prop->name());
    }
}

function main(): void {
    (new ObjPropHolder())->run();
}
?>
--EXPECT--
bool(true)
string(5) "value"
bool(false)
string(5) "value"
string(5) "value"
