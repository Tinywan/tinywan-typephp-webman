--TEST--
ArrayDef supports class value types, subclasses, aliases and dynamic checks
--FILE--
<?php

namespace App {
    class User
    {
        public function __construct(public string $name) {}
    }

    class Admin extends User {}
    class Other {}
}

namespace Demo {
    use App\User as Member;

    class UserCollection
    {
        #[\ArrayDef(Member::class)]
        public array $list = [];

        #[\ArrayDef(\Type::String, \App\User::class)]
        public array $map = [];
    }

    function putList(UserCollection $collection, any $value): void
    {
        $collection->list[] = $value;
    }

    function putMap(UserCollection $collection, any $key, any $value): void
    {
        $collection->map[$key] = $value;
    }
}

namespace {
    function main(): void
    {
        $collection = new Demo\UserCollection();
        $collection->list[] = new App\User('user');
        $collection->list[] = new App\Admin('admin');
        $collection->map['owner'] = new App\Admin('owner');
        Demo\putList($collection, new App\Admin('dynamic-list'));
        Demo\putMap($collection, 'dynamic', new App\User('dynamic-map'));

        foreach ($collection->list as $user) {
            echo $user->name, "\n";
        }
        foreach ($collection->map as $key => $user) {
            echo $key, '=', $user->name, "\n";
        }

        try {
            Demo\putList($collection, new App\Other());
        } catch (TypeError $error) {
            echo "list class checked\n";
        }
        try {
            Demo\putMap($collection, 'bad', new stdClass());
        } catch (TypeError $error) {
            echo "map class checked\n";
        }
    }
}
?>
--EXPECT--
user
admin
dynamic-list
owner=owner
dynamic=dynamic-map
list class checked
map class checked
