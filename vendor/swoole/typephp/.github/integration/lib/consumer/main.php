<?php

declare(strict_types=1);

use TypePhpIntegration\Library\Counter;
use TypePhpIntegration\PeerLibrary\Label;
use function TypePhpIntegration\Library\add;
use function TypePhpIntegration\PeerLibrary\scale;

function main(): void
{
    echo add(19, 23), "\n";

    $counter = new Counter();
    $counter->add(3);
    $counter->add(4);
    echo 'counter=', $counter->value, "\n";

    echo 'scaled=', scale(7), "\n";
    echo 'label=', (new Label('peer'))->render(), "\n";
}
