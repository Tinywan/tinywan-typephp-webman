<?php

class PromotedPrivateSetParent
{
    public function __construct(
        public private(set) string $value,
    ) {
    }
}

class PromotedPrivateSetChild extends PromotedPrivateSetParent
{
    public string $value = 'child';
}
