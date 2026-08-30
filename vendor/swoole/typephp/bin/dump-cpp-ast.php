<?php
shell_exec('clang -Xclang -ast-dump=json  examples/project/ext.cc > test.json');