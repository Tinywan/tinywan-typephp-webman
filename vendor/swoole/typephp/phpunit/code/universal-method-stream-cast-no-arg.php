<?php
function main()
{
    $tmpfile = tempnam(sys_get_temp_dir(), 'aot');
    $fp = fopen($tmpfile, 'w');
    $fp->toStream()->write('data');
}
