<?php

namespace TypePhp\PythonTools\IdeHelper;

use JsonException;
use RuntimeException;
use Throwable;

final class PhpyModuleScanner
{
    private const INSPECTOR = <<<'PYTHON'
import importlib
import inspect
import json

def typephp_parameters(value):
    try:
        result = []
        for parameter in inspect.signature(value).parameters.values():
            kind = str(parameter.kind)
            result.append({
                'name': parameter.name,
                'optional': parameter.default is not inspect._empty or kind == 'KEYWORD_ONLY',
                'variadic': kind in ('VAR_POSITIONAL', 'VAR_KEYWORD'),
            })
        return result
    except Exception:
        return [{'name': 'args', 'optional': True, 'variadic': True}]

def typephp_class(name, value):
    methods = []
    properties = []
    for member in dir(value):
        if not member or member.startswith('_'):
            continue
        try:
            item = getattr(value, member)
            if inspect.isroutine(item):
                parameters = typephp_parameters(item)
                if parameters and parameters[0]['name'] in ('self', 'cls'):
                    parameters.pop(0)
                methods.append({'name': member, 'parameters': parameters})
            else:
                properties.append(member)
        except Exception:
            pass
    return {
        'name': name,
        'parameters': typephp_parameters(value),
        'methods': methods,
        'properties': properties,
    }

module = importlib.import_module(module_name)
metadata = {
    'module': module_name,
    'doc': getattr(module, '__doc__', '') or '',
    'attributes': [],
    'functions': [],
    'classes': [],
}
for name in dir(module):
    if not name or name.startswith('_'):
        continue
    try:
        value = getattr(module, name)
        if inspect.isclass(value):
            metadata['classes'].append(typephp_class(name, value))
        elif inspect.isroutine(value):
            metadata['functions'].append({
                'name': name,
                'parameters': typephp_parameters(value),
            })
        else:
            metadata['attributes'].append({'name': name})
    except Exception:
        pass

metadata_json = json.dumps(metadata, ensure_ascii=False)
PYTHON;

    /** @return array<string, mixed> */
    public function scan(string $moduleName): array
    {
        if (!extension_loaded('phpy')) {
            throw new RuntimeException('The phpy extension is required to generate a Python IDE helper');
        }
        if (preg_match('/^[A-Za-z_]\w*(?:\.[A-Za-z_]\w*)*$/D', $moduleName) !== 1) {
            throw new RuntimeException("Invalid Python module name: {$moduleName}");
        }

        try {
            // Perform reflection entirely in Python. Besides reducing boundary
            // crossings, this avoids retaining PHPy's short-lived Zend method
            // trampolines at AOT call sites.
            $result = \PyCore::eval(self::INSPECTOR, ['module_name' => $moduleName]);
            $json = \PyCore::scalar($result->metadata_json);
            if (!is_string($json)) {
                throw new RuntimeException('Python inspector returned a non-string result');
            }
            $metadata = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($metadata)) {
                throw new RuntimeException('Python inspector returned invalid metadata');
            }
            return $metadata;
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Unable to decode metadata for Python module `{$moduleName}`: {$exception->getMessage()}",
                0,
                $exception,
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Unable to inspect Python module `{$moduleName}`: {$exception->getMessage()}",
                0,
                $exception,
            );
        }
    }
}
