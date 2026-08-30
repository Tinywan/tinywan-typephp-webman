<?php

readonly class WithReadonlyProperty
{
    public function __construct(
        #[With]
        private int $value,
    ) {
    }
}
