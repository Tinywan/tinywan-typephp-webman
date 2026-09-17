<?php

namespace TypePhp\Build;

/** Split complete code-generation entities, never lexically parse C++ bodies. */
trait TranslationUnitSplitTrait
{
    private bool $splitTranslationUnitsEnabled = false;
    /** @var list<string> */
    private array $generatedMethodBodies = [];

    protected function getSplitTranslationUnits(string $source): array
    {
        $primary = $this->getCppFile($source);
        $manifest = $primary . '.parts.json';
        $parts = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : [];
        if (!is_array($parts)) {
            return [];
        }
        // A manifest is disposable cache data, not authority to read/delete
        // arbitrary paths supplied by a damaged or edited cache file.
        return array_values(array_filter($parts, static fn($part): bool => is_string($part)
            && preg_match('/^' . preg_quote($primary, '/') . '\\.part-[0-9]+\\.cc$/D', $part) === 1));
    }

    private function splitLargeTranslationUnit(string $code, string $primary, bool $force): string
    {
        $oldParts = $this->getSplitTranslationUnits($this->file);
        $parts = [];
        if ($this->splitTranslationUnitsEnabled && strlen($code) >= 2 * 1024 * 1024) {
            $groups = [];
            $group = '';
            foreach ($this->generatedMethodBodies as $body) {
                // Keep small helpers together in the primary TU, retaining
                // useful inlining; move only large, complete method entities.
                if (strlen($body) < 8192) {
                    continue;
                }
                $position = strpos($code, $body);
                if ($position === false) {
                    continue;
                }
                $code = substr_replace($code, '', $position, strlen($body));
                if ($group !== '' && strlen($group) + strlen($body) > 384 * 1024) {
                    $groups[] = $group;
                    $group = '';
                }
                $group .= $body . "\n";
            }
            if ($group !== '') {
                $groups[] = $group;
            }
            $includes = $this->genIncludeHeaderFiles();
            foreach ($groups as $index => $body) {
                $data = '';
                foreach ($this->constData as $name => $value) {
                    if (str_contains($body, $name)) {
                        $data .= 'static const unsigned char ' . $name . '[] = {' . $value . "};\n";
                    }
                }
                $part = $primary . '.part-' . $index . '.cc';
                $this->save($includes . $data . "\n" . $body, $part, $force);
                $this->registerGeneratedProjectSource($part);
                $parts[] = $part;
            }
        }
        if ($this->splitTranslationUnitsEnabled || $oldParts !== []) {
            $this->writeFile($primary . '.parts.json', json_encode($parts, JSON_THROW_ON_ERROR));
        }
        foreach (array_diff($oldParts, $parts) as $obsolete) {
            foreach ([$obsolete, $this->getObjectFile($obsolete),
                $this->getMiscObjectCacheMetadataFile($this->getObjectFile($obsolete))] as $artifact) {
                if (is_file($artifact)) {
                    unlink($artifact);
                }
            }
        }
        return $code;
    }
}
