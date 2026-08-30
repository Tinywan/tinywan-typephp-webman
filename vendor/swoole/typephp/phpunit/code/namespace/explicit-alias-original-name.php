<?php

namespace AliasResolution\Imported;

class Notes {}

namespace AliasResolution\Consumer;

use AliasResolution\Imported\Notes as NotesFactory;

class Child extends Notes {}
