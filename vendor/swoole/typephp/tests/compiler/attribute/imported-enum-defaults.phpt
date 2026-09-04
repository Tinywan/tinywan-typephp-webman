--TEST--
Imported enum cases are preserved in property and parameter defaults
--FILE--
<?php

namespace EnumDefaults\Domain {
    enum IdType: string
    {
        case Auto = 'auto';
        case Assigned = 'assigned';
    }
}

namespace EnumDefaults\Metadata {
    use Attribute;
    use EnumDefaults\Domain\IdType;

    #[Attribute(Attribute::TARGET_PROPERTY)]
    class TableId
    {
        public function __construct(public readonly IdType $type = IdType::Auto)
        {
        }
    }
}

namespace EnumDefaults\Model {
    use EnumDefaults\Domain\IdType;
    use EnumDefaults\Metadata\TableId;

    class Record
    {
        #[TableId(IdType::Assigned)]
        public IdType $explicit = IdType::Assigned;

        #[TableId]
        public IdType $default = IdType::Auto;

        public function __construct(public readonly IdType $promoted = IdType::Auto)
        {
        }

        public function select(IdType $type = IdType::Auto): IdType
        {
            return $type;
        }
    }
}

namespace {
    use EnumDefaults\Domain\IdType;
    use EnumDefaults\Metadata\TableId;
    use EnumDefaults\Model\Record;

    function main(): void
    {
        $record = new Record();
        var_dump($record->explicit === IdType::Assigned);
        var_dump($record->default === IdType::Auto);
        var_dump($record->promoted === IdType::Auto);
        var_dump($record->select() === IdType::Auto);

        $reflection = new ReflectionClass(Record::class);
        $explicit = $reflection->getProperty('explicit')->getAttributes(TableId::class)[0];
        var_dump($explicit->getArguments()[0] === IdType::Assigned);
        var_dump($explicit->newInstance()->type === IdType::Assigned);

        $default = $reflection->getProperty('default')->getAttributes(TableId::class)[0];
        var_dump($default->getArguments());
        var_dump($default->newInstance()->type === IdType::Auto);

        $parameter = $reflection->getMethod('select')->getParameters()[0];
        var_dump($parameter->getDefaultValue() === IdType::Auto);
    }
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
array(0) {
}
bool(true)
bool(true)
