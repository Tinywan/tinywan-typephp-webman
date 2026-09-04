<?php

namespace ImportAliasDomains\First;

use Vendor\Package\Route;
use function Vendor\Package\dispatch as Route;
use const Vendor\Package\ROUTE as Route;
use const Vendor\Package\route as route;

class Consumer {}

namespace ImportAliasDomains\Second;

// Import aliases are local to a namespace block.
use Another\Package\Route;

class Consumer {}
