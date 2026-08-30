--TEST--
toArray() method dispatch: (array) cast calls toArray() instead of reading property table
--FILE--
<?php

class User {
    public int $id;
    public string $name;

    public function __construct(int $id, string $name) {
        $this->id = $id;
        $this->name = $name;
    }

    public function toArray(): array {
        return [
            'uid' => $this->id,
            'display_name' => $this->name,
            'source' => 'toArray',
        ];
    }
}

class PlainUser {
    public int $id;
    public string $name;

    public function __construct(int $id, string $name) {
        $this->id = $id;
        $this->name = $name;
    }
}

function main() {
    $user = new User(1, 'admin');
    // (array) cast should call User::toArray(), not read properties
    $arr = (array) $user;
    var_dump($arr['uid']);
    var_dump($arr['display_name']);
    var_dump($arr['source']);

    // no toArray() method — falls back to property table
    $plain = new PlainUser(2, 'guest');
    $arr2 = (array) $plain;
    var_dump($arr2['id']);
    var_dump($arr2['name']);
}

?>
--EXPECT--
int(1)
string(5) "admin"
string(7) "toArray"
int(2)
string(5) "guest"
