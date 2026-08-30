<?php
function main()
{
    $tmpfile = tempnam(sys_get_temp_dir(), 'aot');
    fopen($tmpfile, 'w')->toStream()->unknownMethod();
}
