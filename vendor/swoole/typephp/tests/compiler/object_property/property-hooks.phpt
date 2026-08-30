--TEST--
PHP 8.4 property hooks lower to getter and setter methods
--FILE--
<?php

class Person
{
    public string $name {
        get => strtoupper($this->name);
        set(string $value) => trim($value);
    }

    public int $age {
        get {
            return $this->age + 1;
        }
        set(int $value) {
            $this->age = $value;
        }
    }
}

function setNameDynamically(mixed $person): void
{
    $person->name = ' bob ';
}

function main(): void
{
    $person = new Person();
    $person->name = ' alice ';
    $person->age = 20;
    setNameDynamically($person);
    var_dump($person->name);
    var_dump($person->age);
}
?>
--EXPECT--
string(3) "BOB"
int(21)
