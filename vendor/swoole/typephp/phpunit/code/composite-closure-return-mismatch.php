<?php

function composite_closure_return(): void
{
    $callback = function (): int|string {
        return [];
    };
}
