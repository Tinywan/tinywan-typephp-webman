<?php

declare(strict_types=1);

function typephp_integration_probe(int $request): string
{
    static $calls = 0;
    ++$calls;

    // DateTimeImmutable and its format() method are internal, module-lifetime
    // symbols. The host class/function below are rebuilt for every request and
    // therefore exercise the request-lifetime class/function cache domain.
    $date = new DateTimeImmutable('@' . $request);
    $value = new TypePhpIntegrationRequestValue($request);
    return $calls . '@' . $date->format('U') . '|'
        . typephp_integration_request_transform($value->render());
}

// main() belongs to the embedded bin entry path. An extension must neither
// register it as a PHP function nor invoke it from RINIT.
function main(): void
{
    throw new RuntimeException('ext mode invoked bin main()');
}
