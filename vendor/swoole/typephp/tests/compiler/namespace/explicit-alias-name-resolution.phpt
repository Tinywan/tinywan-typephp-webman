--TEST--
Explicit class aliases do not import the target short name
--FILE--
<?php
namespace AliasResolution\Imported {
    const VALUE = 'qualified-constant';

    function label(): string
    {
        return 'qualified-function';
    }

    class Notes
    {
        public const string KIND = 'imported';

        public function source(): string
        {
            return 'imported';
        }
    }
}

namespace AliasResolution\Consumer {
    use AliasResolution\Imported as ImportedNamespace;
    use AliasResolution\Imported\Notes as NotesFactory;

    class Notes
    {
        public const string KIND = 'local';

        public function source(): string
        {
            return 'local';
        }
    }

    class LocalChild extends Notes {}
    class ImportedChild extends nOtEsFaCtOrY {}

    class Holder
    {
        public Notes $local;
        public NOTESFACTORY $imported;

        public function __construct(Notes $local, notesfactory $imported)
        {
            $this->local = $local;
            $this->imported = $imported;
        }
    }

    function run(): void
    {
        $holder = new Holder(new LocalChild(), new ImportedChild());
        var_dump($holder->local->source());
        var_dump($holder->imported->source());
        var_dump(Notes::KIND);
        var_dump(notesfactory::KIND);
        var_dump(ImportedNamespace\label());
        var_dump(ImportedNamespace\VALUE);
    }
}

namespace AliasResolution\Grouped {
    use AliasResolution\Imported\{Notes as GroupedFactory};

    class Notes
    {
        public function source(): string
        {
            return 'group-local';
        }
    }

    class LocalChild extends Notes {}
    class ImportedChild extends gRoUpEdFaCtOrY {}

    function run(): void
    {
        var_dump((new LocalChild())->source());
        var_dump((new ImportedChild())->source());
    }
}

namespace {
    function main(): void
    {
        AliasResolution\Consumer\run();
        AliasResolution\Grouped\run();
    }
}
?>
--EXPECT--
string(5) "local"
string(8) "imported"
string(5) "local"
string(8) "imported"
string(18) "qualified-function"
string(18) "qualified-constant"
string(11) "group-local"
string(8) "imported"
