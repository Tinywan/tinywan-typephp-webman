--TEST--
Static Class Property Read/Write Test
--FILE--
<?php

include __DIR__ . '/../static_property_test.inc';

// Test reading static properties
echo "Initial public property: " . TestClass::$public_static_property . "\n";
echo "Initial protected property: " . TestClass::getProtectedProperty() . "\n";
echo "Initial private property: " . TestClass::getPrivateProperty() . "\n";
echo "Initial default property: " . TestClass::$default_static_property . "\n";

// Test writing static properties
TestClass::$public_static_property = 'modified_public';
TestClass::setProtectedProperty('modified_protected');
TestClass::setPrivateProperty('modified_private');
TestClass::$default_static_property = 'modified_default';

// Test reading static properties after modification
echo "Modified public property: " . TestClass::$public_static_property . "\n";
echo "Modified protected property: " . TestClass::getProtectedProperty() . "\n";
echo "Modified private property: " . TestClass::getPrivateProperty() . "\n";
echo "Modified default property: " . TestClass::$default_static_property . "\n";

echo "Initial counter: " . AnotherClass::$counter . "\n";
echo "After increment 1: " . AnotherClass::increment() . "\n";
echo "After increment 2: " . AnotherClass::increment() . "\n";
echo "Final counter: " . AnotherClass::$counter . "\n";

echo "Int property: " . DataTypeTest::$int_prop . "\n";
echo "Float property: " . DataTypeTest::$float_prop . "\n";
echo "Bool property: " . (DataTypeTest::$bool_prop ? 'true' : 'false') . "\n";

DataTypeTest::$int_prop = 100;
DataTypeTest::$float_prop = 2.71;
DataTypeTest::$bool_prop = false;

echo "Modified int property: " . DataTypeTest::$int_prop . "\n";
echo "Modified float property: " . DataTypeTest::$float_prop . "\n";
echo "Modified bool property: " . (DataTypeTest::$bool_prop ? 'true' : 'false') . "\n";
?>
--EXPECTF--
Initial public property: initial_value
Initial protected property: protected_value
Initial private property: private_value
Initial default property: default_value
Modified public property: modified_public
Modified protected property: modified_protected
Modified private property: modified_private
Modified default property: modified_default
Initial counter: 0
After increment 1: 1
After increment 2: 2
Final counter: 2
Int property: 42
Float property: %s
Bool property: true
Modified int property: 100
Modified float property: 2.71
Modified bool property: false