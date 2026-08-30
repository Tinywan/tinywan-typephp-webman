<?php

namespace MethodsForAttribute {

use \MethodsFor as ForType;
use \Type;

#[ForType(Type::String)]
class StringMethods
{
    public static function surround(string $value, string $left, string $right): string
    {
        return $left . $value . $right;
    }
}

class User
{
    public string $name = 'TypePHP';
}

#[\MethodsFor(User::class)]
class UserMethods
{
    public static function displayName(User $user): string
    {
        return $user->name;
    }
}

}

namespace {

function main(): void
{
    $name = 'TypePHP';
    echo $name->surround('<', '>');
    echo (new \MethodsForAttribute\User())->displayName();
}

}
