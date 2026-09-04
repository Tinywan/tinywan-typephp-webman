<?php
function main() {
    $f = function (): Traversable&callable {};
    $f();
}
