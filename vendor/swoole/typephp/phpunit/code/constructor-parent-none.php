<?php

class ConstructorWithoutParentConstructor
{
}

class ConstructorWithoutParentConstructorChild extends ConstructorWithoutParentConstructor
{
    #[Constructor]
    private int $id;
}
