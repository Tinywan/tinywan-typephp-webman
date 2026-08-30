<?php
function bar(): void {
    var_dump(__FUNCTION__);
}

function main()
{
    var_dump(bar()->toString());
}
