<?php
class TypedArrayPropertyExpectedUser {}
class TypedArrayPropertyOtherUser {}
class TypedArrayPropertyClassMismatchBox
{
    #[StdList(TypedArrayPropertyExpectedUser::class)]
    public array $users = [];
}
function typedArrayPropertyStaticClassMismatch(TypedArrayPropertyClassMismatchBox $box): void
{
    $box->users[] = new TypedArrayPropertyOtherUser();
}
