<?php

class LoopControlTest extends \BaseTest
{
    public function testContinueInStandaloneSwitchIsRejected(): void
    {
        $this->exec(
            'Cannot continue outside loop',
            'control-flow/standalone-switch-continue.php',
        );
    }

    public function testContinueInStandaloneDynamicSwitchIsRejected(): void
    {
        $this->exec(
            'Cannot continue outside loop',
            'control-flow/standalone-dynamic-switch-continue.php',
        );
    }

    public function testContinueInSwitchNestedInLoopsIsAllowed(): void
    {
        $this->compile('control-flow/loop-switch-continue.php');
    }

    public function testWhileConditionIsStillParsedBeforeItsBody(): void
    {
        $this->exec(
            'Undefined variable `$results`',
            'control-flow/while-body-defined-condition.php',
        );
    }
}
