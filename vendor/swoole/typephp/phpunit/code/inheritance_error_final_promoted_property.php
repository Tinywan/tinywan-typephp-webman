<?php

class FinalPromotedPropertyParent
{
    public function __construct(
        public final string $value,
    ) {
    }
}

class FinalPromotedPropertyChild extends FinalPromotedPropertyParent
{
    public string $value = 'child';
}
