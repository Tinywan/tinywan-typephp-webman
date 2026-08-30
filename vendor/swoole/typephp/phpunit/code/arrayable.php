<?php

class ArrayableBase
{
    public int $baseId = 1;
    protected string $hidden = 'hidden';
}

#[Arrayable(fields: ['name', 'baseId'])]
#[Printer(['name'])]
class ArrayableUser extends ArrayableBase
{
    public string $name = '张三';
    public string $email = 'user@example.com';
}

#[Arrayable]
class ArrayableDefaults extends ArrayableBase
{
    public string $name = 'default';
    public static int $shared = 1;
}

#[Arrayable([])]
class EmptyArrayable
{
    public int $id = 1;
}

#[Arrayable(['lateId'])]
class LateFieldArrayable extends LateFieldBase
{
}

class LateFieldBase
{
    public int $lateId = 9;
}

#[Arrayable]
class LateCustomArrayable extends LateCustomArrayableBase
{
    public int $id = 1;
}

class LateCustomArrayableBase
{
    public function toArray(): array
    {
        return ['custom' => 8];
    }
}

function main(): void
{
    $user = new ArrayableUser();
    $data = $user->toArray();
    echo $data['name'];
    echo $data['baseId'];
    echo count($data);
    echo $user;
    echo $user->toString();

    $defaults = (new ArrayableDefaults())->toArray();
    echo $defaults['baseId'];
    echo $defaults['name'];
    echo count($defaults);
    echo count((new EmptyArrayable())->toArray());
    echo (new LateFieldArrayable())->toArray()['lateId'];
    echo (new LateCustomArrayable())->toArray()['custom'];
}
