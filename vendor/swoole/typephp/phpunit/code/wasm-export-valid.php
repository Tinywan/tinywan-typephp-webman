<?php

#[WasmExport(name: 'greet-user')]
function greetUser(string $name): string
{
    return "Hello, $name";
}
