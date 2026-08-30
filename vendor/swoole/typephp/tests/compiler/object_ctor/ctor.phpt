--TEST--
ctor
--FILE--
<?php
namespace Test {
    #[AllowDynamicProperties]
    class Worker
    {
        protected static array $globalStatistics = [
            'start_timestamp' => 0,
            'worker_exit_info' => []
        ];
        public ?string $workerId = null;
        public function __construct(?string $socketName = null)
        {
            $this->workerId = spl_object_hash($this);
            var_dump($socketName);
            var_dump(self::$globalStatistics);
        }
    }
}
namespace  {
    function main()
    {
        $obj = new \Test\Worker("hello");
        var_dump($obj->workerId);
    }
}
?>
--EXPECT--
string(5) "hello"
array(2) {
  ["start_timestamp"]=>
  int(0)
  ["worker_exit_info"]=>
  array(0) {
  }
}
string(32) "00000000000000010000000000000000"