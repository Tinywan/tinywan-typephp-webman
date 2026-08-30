<?php
function main()
{
    $o = new stdClass();
    $o->prop = ['dim2' => ['dim3' => 'value']];
    $o->prop['dim2']['dim3'] = 'hello';

    var_dump($o);
}


