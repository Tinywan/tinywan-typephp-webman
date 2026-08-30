--TEST--
Symfony Config pattern: loader exception resource formatting and bundle hints
--FILE--
<?php

function resource_to_string(mixed $var): string
{
    if (is_object($var)) {
        return sprintf('Object(%s)', $var::class);
    }

    if (is_array($var)) {
        $parts = [];
        foreach ($var as $k => $v) {
            $parts[] = sprintf('%s => %s', $k, resource_to_string($v));
        }

        return sprintf('Array(%s)', implode(', ', $parts));
    }

    if (is_resource($var)) {
        return sprintf('Resource(%s)', get_resource_type($var));
    }

    if (null === $var) {
        return 'null';
    }

    return (string) $var;
}

function build_loader_message(mixed $resource, ?Throwable $previous = null, ?string $sourceResource = null, ?string $type = null): string
{
    if (!is_string($resource)) {
        try {
            $resource = json_encode($resource, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $resource = sprintf('resource of type "%s"', get_debug_type($resource));
        }
    }

    $message = '';
    if ($previous) {
        if (str_ends_with($previous->getMessage(), '.')) {
            $message .= sprintf('%s', substr($previous->getMessage(), 0, -1)).' in ';
        } else {
            $message .= sprintf('%s', $previous->getMessage()).' in ';
        }
        $message .= $resource.' ';
        $message .= null === $sourceResource
            ? sprintf('(which is loaded in resource "%s")', $resource)
            : sprintf('(which is being imported from "%s")', $sourceResource);
        $message .= '.';
    } elseif (null === $sourceResource) {
        $message .= sprintf('Cannot load resource "%s".', $resource);
    } else {
        $message .= sprintf('Cannot import resource "%s" from "%s".', $resource, $sourceResource);
    }

    if ('@' === $resource[0]) {
        $parts = explode(DIRECTORY_SEPARATOR, $resource);
        $bundle = substr($parts[0], 1);
        $message .= sprintf(' Make sure the "%s" bundle is registered.', $bundle);
    } elseif (null !== $type) {
        $message .= sprintf(' Make sure there is a loader supporting the "%s" type.', $type);
    }

    return $message;
}

final class ResourceObject
{
}

function main(): void
{
    $handle = fopen('php://memory', 'r');
    var_dump(resource_to_string(['handle' => $handle, 'object' => new ResourceObject(), 'none' => null]));
    var_dump(build_loader_message('@DemoBundle/config.yaml', new RuntimeException('broken.'), null));
    var_dump(build_loader_message(['config' => new ResourceObject()], null, 'services.yaml', 'yaml'));
}
?>
--EXPECTF--
string(%d) "Array(handle => Resource(stream), object => Object(ResourceObject), none => null)"
string(%d) "broken in @DemoBundle/config.yaml (which is loaded in resource "@DemoBundle/config.yaml"). Make sure the "DemoBundle" bundle is registered."
string(%d) "Cannot import resource "{"config":{}}" from "services.yaml". Make sure there is a loader supporting the "yaml" type."
