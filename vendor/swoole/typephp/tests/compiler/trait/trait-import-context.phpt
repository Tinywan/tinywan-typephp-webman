--TEST--
Trait methods retain their declaring file import context after AST composition
--FILE--
<?php
namespace TraitImports\Support {
    class Marker {
        public string $value = 'class';
    }

    function imported_label(): string {
        return 'function';
    }

    const IMPORTED_VALUE = 'constant';
}

namespace TraitImports\Template {
    use TraitImports\Support\Marker as ImportedMarker;
    use function TraitImports\Support\imported_label as importedLabel;
    use const TraitImports\Support\IMPORTED_VALUE as importedValue;

    trait ImportedNames {
        public function values(): array {
            $marker = new ImportedMarker();
            return [$marker->value, importedLabel(), importedValue, self::SELF_VALUE];
        }
    }
}

namespace TraitImports\Consumer {
    use TraitImports\Template\ImportedNames;

    class Example {
        use ImportedNames;
        public const string SELF_VALUE = 'self';
    }
}

namespace {
    function main(): void {
        var_dump((new TraitImports\Consumer\Example())->values());
    }
}
?>
--EXPECT--
array(4) {
  [0]=>
  string(5) "class"
  [1]=>
  string(8) "function"
  [2]=>
  string(8) "constant"
  [3]=>
  string(4) "self"
}
