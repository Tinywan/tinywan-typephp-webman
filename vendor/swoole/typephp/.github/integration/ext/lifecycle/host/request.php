<?php

declare(strict_types=1);

$request = PHP_SAPI === 'cli'
    ? (int) getenv('TYPEPHP_INTEGRATION_REQUEST')
    : (int) ($_GET['request'] ?? 0);

// Alternate the implementation attached to the same request-local symbols.
// A stale pointer surviving RSHUTDOWN will either call the preceding request's
// implementation, access released memory, or crash the long-running host.
if ($request % 2 === 0) {
    final class TypePhpIntegrationRequestValue
    {
        public function __construct(private int $value)
        {
        }

        public function render(): string
        {
            return 'even:' . $this->value;
        }
    }

    function typephp_integration_request_transform(string $value): string
    {
        return 'even-handler[' . $value . ']';
    }
} else {
    final class TypePhpIntegrationRequestValue
    {
        public function __construct(private int $value)
        {
        }

        public function render(): string
        {
            return 'odd:' . $this->value;
        }
    }

    function typephp_integration_request_transform(string $value): string
    {
        return 'odd-handler[' . $value . ']';
    }
}

header('Content-Type: application/json');
echo json_encode([
    'request' => $request,
    'results' => [
        typephp_integration_probe($request),
        typephp_integration_probe($request),
    ],
    'peer_results' => [
        typephp_integration_peer_probe($request),
        typephp_integration_peer_probe($request),
    ],
    'extensions_loaded' => [
        extension_loaded('typephp_integration_ext_primary'),
        extension_loaded('typephp_integration_ext_peer'),
    ],
    'main_registered' => function_exists('main'),
    'pid' => getmypid(),
], JSON_THROW_ON_ERROR);
