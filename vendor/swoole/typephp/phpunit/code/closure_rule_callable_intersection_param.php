<?php
function main() {
    $f = function (Traversable&callable $x): void {};
    $f(null);
}
