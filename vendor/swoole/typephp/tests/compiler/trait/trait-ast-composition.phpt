--TEST--
Trait methods are compiled as class methods for every consuming class
--FILE--
<?php
trait InnerTemplate {
    protected int $value = 0;

    public function className(): string {
        return __CLASS__;
    }

    public function traitName(): string {
        return __TRAIT__;
    }

    public function setValue(int $value): void {
        $this->value = $value;
    }

    public function getValue(): int {
        return $this->value;
    }
}

trait OuterTemplate {
    use InnerTemplate;
}

class FirstConsumer {
    use OuterTemplate;
}

class SecondConsumer {
    use OuterTemplate { getValue as readValue; }
}

function main() {
    $first = new FirstConsumer();
    $second = new SecondConsumer();
    $first->setValue(11);
    $second->setValue(22);
    var_dump($first->className(), $first->traitName(), $first->getValue());
    var_dump($second->className(), $second->traitName(), $second->readValue());
}
?>
--EXPECT--
string(13) "FirstConsumer"
string(13) "InnerTemplate"
int(11)
string(14) "SecondConsumer"
string(13) "InnerTemplate"
int(22)
