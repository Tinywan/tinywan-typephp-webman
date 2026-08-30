--TEST--
type hits: instance property type check message includes class name
--FILE--
<?php

class TypeHitPropertyMessage
{
    public int|string $union;

    public function setInvalid(): void
    {
        try {
            $this->union = any(null);
        } catch (TypeError $e) {
            var_dump($e->getMessage());
        }
    }
}

function main()
{
    (new TypeHitPropertyMessage())->setInvalid();
}
?>
--EXPECT--
string(69) "TypeHitPropertyMessage::$union must be of type int|string, null given"
