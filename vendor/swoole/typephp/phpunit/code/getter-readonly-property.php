<?php

readonly class GetterReadonlyProperty
{
    public function __construct(
        #[Getter]
        private int $value,
    ) {
    }
}
