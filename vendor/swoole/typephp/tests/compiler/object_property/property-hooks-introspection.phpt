--TEST--
PHP 8.4 object introspection invokes property get hooks
--FILE--
<?php

final class IntrospectedPropertyHooks
{
    private string $stored = 'initial';

    public string $backed = 'raw' {
        get => strtoupper($this->backed);
        set (string $value) {
            $this->backed = trim($value);
        }
    }

    public string $virtual {
        get => 'virtual:' . $this->stored;
        set (string $value) {
            $this->stored = $value;
        }
    }
}

function main(): void
{
    $object = new IntrospectedPropertyHooks();
    $object->backed = ' value ';
    $object->virtual = 'changed';

    echo json_encode($object), "\n";
    var_dump(get_object_vars($object));
    foreach ($object as $name => $value) {
        echo $name, '=', $value, "\n";
    }

    // PHP serialization exposes backing storage, not computed virtual values.
    echo serialize($object), "\n";
}
?>
--EXPECTF--
{"backed":"VALUE","virtual":"virtual:changed"}
array(2) {
  ["backed"]=>
  string(5) "VALUE"
  ["virtual"]=>
  string(15) "virtual:changed"
}
backed=VALUE
virtual=virtual:changed
O:25:"IntrospectedPropertyHooks":2:{s:33:"%0IntrospectedPropertyHooks%0stored";s:7:"changed";s:6:"backed";s:5:"value";}
