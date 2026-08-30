<?php
function main()
{
    $homepage = file_get_contents('https://www.example.com/');
    echo $homepage;
}
