--TEST--
Anonymous class method bodies preserve namespace imports
--FILE--
<?php
namespace AnonymousClassSupport {
    const FLAG = 'imported';

    class Subject {}

    class Marker {
        public const string VALUE = 'resolved';
    }

    function accepts(Subject $value): bool {
        return true;
    }

    class Visitor {
        public function inspect(object $value) {
            return null;
        }
    }
}

namespace AnonymousClassConsumer {
    use AnonymousClassSupport\Marker as ImportedMarker;
    use AnonymousClassSupport\Subject as ImportedSubject;
    use AnonymousClassSupport\Visitor as ImportedVisitor;
    use const AnonymousClassSupport\FLAG as IMPORTED_FLAG;
    use function AnonymousClassSupport\accepts as imported_accepts;

    function main(): void {
        $visitor = new class('ready') extends ImportedVisitor {
            public function __construct(private readonly string $state) {}

            public function accepts(object $value): bool {
                return $value instanceof ImportedSubject
                    && ImportedMarker::VALUE === 'resolved'
                    && IMPORTED_FLAG === 'imported'
                    && imported_accepts($value)
                    && $this->state === 'ready';
            }

            public function inspect(object $value) {
                if ($value instanceof ImportedSubject) {
                    return 'subject';
                }
            }
        };

        var_dump($visitor->accepts(new ImportedSubject()));
        var_dump($visitor->inspect(new \stdClass()));
    }
}

namespace {
    function main(): void {
        AnonymousClassConsumer\main();
    }
}
?>
--EXPECT--
bool(true)
NULL
