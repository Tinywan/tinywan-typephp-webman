<?php

declare(strict_types=1);

class Base
{
    public function __construct(array $option = [])
    {
        echo 'Base:';
        foreach ($option as $k => $v) {
            echo ' ' . $k . '=' . $v;
        }
        echo "\n";
    }
}

trait TPdoDriver
{
    public function __construct(array $option = [])
    {
        $option['fromTrait'] = 1;
        parent::__construct($option);
    }
}

class Driver extends Base
{
    use TPdoDriver {
        __construct as private tPdoDriverConstruct;
    }

    public function __construct(array $option = [])
    {
        $option['username'] = 'postgres';
        $this->tPdoDriverConstruct($option);
    }
}

class DirectDriver extends Base
{
    use TPdoDriver;
}
