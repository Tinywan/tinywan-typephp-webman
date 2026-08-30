--TEST--
strict scalar declarations validate dynamic parameter and return values
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

declare(strict_types=1);

function acceptsInt(int $value): int { return $value; }
function acceptsFloat(float $value): float { return $value; }
function acceptsBool(bool $value): bool { return $value; }
function acceptsString(string $value): string { return $value; }

function invalidIntReturn(): int { return json_decode('"bad"'); }
function invalidFloatReturn(): float { return json_decode('"bad"'); }
function invalidBoolReturn(): bool { return json_decode('1'); }
function invalidStringReturn(): string { return json_decode('1'); }
function widenedFloatReturn(): float { return json_decode('7'); }

function main(): void
{
    $string = json_decode('"bad"');
    $integer = json_decode('1');

    try { acceptsInt($string); echo "int-param=accepted\n"; }
    catch (TypeError $error) { echo "int-param=TypeError\n"; }
    try { acceptsFloat($string); echo "float-param=accepted\n"; }
    catch (TypeError $error) { echo "float-param=TypeError\n"; }
    try { acceptsBool($integer); echo "bool-param=accepted\n"; }
    catch (TypeError $error) { echo "bool-param=TypeError\n"; }
    try { acceptsString($integer); echo "string-param=accepted\n"; }
    catch (TypeError $error) { echo "string-param=TypeError\n"; }

    try { invalidIntReturn(); echo "int-return=accepted\n"; }
    catch (TypeError $error) { echo "int-return=TypeError\n"; }
    try { invalidFloatReturn(); echo "float-return=accepted\n"; }
    catch (TypeError $error) { echo "float-return=TypeError\n"; }
    try { invalidBoolReturn(); echo "bool-return=accepted\n"; }
    catch (TypeError $error) { echo "bool-return=TypeError\n"; }
    try { invalidStringReturn(); echo "string-return=accepted\n"; }
    catch (TypeError $error) { echo "string-return=TypeError\n"; }

    $dynamicCall = 'acceptsInt';
    try { $dynamicCall($string); echo "dynamic-param=accepted\n"; }
    catch (TypeError $error) { echo "dynamic-param=TypeError\n"; }

    var_dump(acceptsInt(json_decode('7')));
    var_dump(acceptsFloat(json_decode('1.5')));
    var_dump(acceptsFloat(json_decode('7')));
    var_dump(acceptsBool(json_decode('true')));
    var_dump(acceptsString(json_decode('"ok"')));
    var_dump(widenedFloatReturn());
}
?>
--EXPECT--
int-param=TypeError
float-param=TypeError
bool-param=TypeError
string-param=TypeError
int-return=TypeError
float-return=TypeError
bool-return=TypeError
string-return=TypeError
dynamic-param=TypeError
int(7)
float(1.5)
float(7)
bool(true)
string(2) "ok"
float(7)
