<?php

namespace Collision {
    class Worker
    {
        public function validate(array $data = []): void
        {
        }
    }
}

namespace Collision\Worker {
    function validate(mixed $validater): mixed
    {
        return $validater;
    }

    function invoke_validate(mixed $validater): mixed
    {
        return validate($validater);
    }
}
