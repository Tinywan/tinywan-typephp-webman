<?php
// A cyclic extends graph can only be expressed ahead-of-time (Zend never
// gets this far: the first declaration already fails with "Interface "B"
// not found"). The compiler must fail promptly instead of recursing.
interface A extends B {}
interface B extends A {}

function main() {}
