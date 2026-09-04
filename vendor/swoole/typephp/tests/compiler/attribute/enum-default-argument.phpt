--TEST--
Attribute constructor defaults and arguments preserve backed enum cases
--FILE--
<?php

enum Status: string
{
    case Active = 'active';
}

#[Attribute(Attribute::TARGET_CLASS)]
class ValidateStatus
{
    public function __construct(public Status $status = Status::Active)
    {
    }
}

#[ValidateStatus]
class ActiveResource
{
}

#[ValidateStatus(Status::Active)]
class ExplicitActiveResource
{
}

#[ValidateStatus(true ? Status::Active : Status::Active)]
class ConditionalActiveResource
{
}

function main(): void
{
    foreach ([
        ActiveResource::class,
        ExplicitActiveResource::class,
        ConditionalActiveResource::class,
    ] as $class) {
        $attribute = (new ReflectionClass($class))->getAttributes(ValidateStatus::class)[0];
        $validation = $attribute->newInstance();

        var_dump($attribute->getArguments());
        var_dump($validation->status === Status::Active);
        var_dump($validation->status->name);
        var_dump($validation->status->value);
    }
}
?>
--EXPECT--
array(0) {
}
bool(true)
string(6) "Active"
string(6) "active"
array(1) {
  [0]=>
  enum(Status::Active)
}
bool(true)
string(6) "Active"
string(6) "active"
array(1) {
  [0]=>
  enum(Status::Active)
}
bool(true)
string(6) "Active"
string(6) "active"
