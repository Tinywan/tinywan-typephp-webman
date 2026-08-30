--TEST--
use alias for class names
--FILE--
<?php
namespace Library\Storage\Drivers {
    class FileDriver {
        public function read(): string {
            return "file:data";
        }
    }

    class MemoryDriver {
        public function read(): string {
            return "memory:data";
        }
    }
}

namespace {
    use Library\Storage\Drivers\FileDriver as FS;
    use Library\Storage\Drivers\MemoryDriver as Mem;

    function main() {
        $fs = new FS();
        $mem = new Mem();
        var_dump($fs->read());
        var_dump($mem->read());
        echo "done\n";
    }
}
?>
--EXPECT--
string(9) "file:data"
string(11) "memory:data"
done
