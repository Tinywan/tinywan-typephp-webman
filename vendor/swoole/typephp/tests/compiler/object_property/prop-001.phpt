--TEST--
Dynamic object property assignment with short-circuit evaluation
--FILE--
<?php
class Worker
{
    public $context;
}

function originalLogic($worker, $prop)
{
    !isset($worker->$prop)
        && !isset($worker->context->$prop)
        && $worker->context->$prop = 'NNNN';
}

function main()
{
    $prop = 'name';
    ini_set('error_reporting', E_ERROR | E_WARNING);

    $worker = new Worker();
    $worker->context = new stdClass();
    originalLogic($worker, $prop);
    var_dump($worker->context->name);

    $worker = new Worker();
    $worker->context = new stdClass();
    $worker->name = 'Alice';
    originalLogic($worker, $prop);
    var_dump(isset($worker->context->name));

    $worker = new Worker();
    $worker->context = new stdClass();
    $worker->context->name = 'Bob';
    originalLogic($worker, $prop);
    var_dump($worker->context->name);
}
?>
--EXPECT--
string(4) "NNNN"
bool(false)
string(3) "Bob"
