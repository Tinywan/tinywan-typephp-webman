<?php

function native_anonymous_class(): void
{
    $value = new #[Native] class {
        public int $value = 1;
    };
}
