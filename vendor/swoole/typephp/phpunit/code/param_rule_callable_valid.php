<?php
function apply(callable $fn): void {
    $fn();
}

function main() {
    apply(function (): void {});
}
