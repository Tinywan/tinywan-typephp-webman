<?php
class TypedObjectPropBase
{
}

class TypedObjectPropChild extends TypedObjectPropBase
{
}

class TypedObjectPropHolder
{
    public TypedObjectPropBase $prop;

    public function set(): void
    {
        $this->prop = new TypedObjectPropChild();
    }
}

function main(): void
{
    (new TypedObjectPropHolder())->set();
}
