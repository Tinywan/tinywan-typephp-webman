<?php
function main()
{
    $arr = [];
    $arr->push(1);
    ($arr->slice(0, 1))->push(2);
}
