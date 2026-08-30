<?php

declare(strict_types=1);

#[WasmExport(name: 'get-demo-report')]
function getDemoReport(string $argumentsJson, string $greeting, string $stdin): string
{
    $arguments = json_decode($argumentsJson, true, flags: JSON_THROW_ON_ERROR);
    $report = WasiDemo::report($arguments, $greeting, $stdin);
    return json_encode(
        $report,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
    );
}

#[WasmExport(name: 'get-extension-info')]
function getExtensionInfo(string $extension): string
{
    return json_encode(
        WasiDemo::extensionInfo($extension),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
    );
}
