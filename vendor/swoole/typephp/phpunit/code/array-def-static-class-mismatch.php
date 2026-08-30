<?php
class ArrayDefExpectedUser {}
class ArrayDefOtherUser {}
class ArrayDefClassMismatchBox
{
    #[ArrayDef(ArrayDefExpectedUser::class)]
    public array $users = [];
}
function arrayDefStaticClassMismatch(ArrayDefClassMismatchBox $box): void
{
    $box->users[] = new ArrayDefOtherUser();
}
