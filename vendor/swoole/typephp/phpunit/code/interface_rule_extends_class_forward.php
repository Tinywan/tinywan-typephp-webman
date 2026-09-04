<?php
// The parent's declaration appears after the interface, so only the
// translation phase can know its kind.
interface Late extends Impl {}
class Impl {}

function main() {}
