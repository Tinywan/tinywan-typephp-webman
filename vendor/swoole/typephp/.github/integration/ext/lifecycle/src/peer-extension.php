<?php

declare(strict_types=1);

function typephp_integration_peer_probe(int $request): string
{
    static $calls = 0;
    ++$calls;

    // Resolve the same request-local symbols as the primary extension. Each
    // module must keep its own cache slots while sharing the PHPX request.
    $date = new DateTimeImmutable('@' . $request);
    $value = new TypePhpIntegrationRequestValue($request);
    return 'peer-' . $calls . '@' . $date->format('U') . '|'
        . typephp_integration_request_transform($value->render());
}

// Both shared objects contain the same hidden generated php_main symbol. Only
// get_module and the module-specific Zend entry points may be visible outside
// their respective DSO.
function main(): void
{
    throw new RuntimeException('peer ext mode invoked bin main()');
}
