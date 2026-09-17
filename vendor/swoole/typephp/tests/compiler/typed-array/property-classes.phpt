--TEST--
TypedProperty supports class value types, subclasses, aliases and statically typed writes
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
        #[\StdList(Member::class)]
        public array $list = [];

        #[\StdDict(\Type::String, \App\User::class)]
        public array $map = [];
    }

    function putList(UserCollection $collection, \App\User $value): void
    {
        $collection->list[] = $value;
    }

    function putMap(UserCollection $collection, any $key, \App\User $value): void
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

    }
}
?>
--EXPECT--
user
admin
dynamic-list
owner=owner
dynamic=dynamic-map
