<?php

/**
 * Initialize the JVM with a classpath.
 * Must be called once before any other jni_* functions.
 */
function jni_init(string $classpath = "."): void
{

}

/**
 * Destroy the JVM and release all resources.
 */
function jni_destroy(): void
{

}

/**
 * Find a Java class by name (e.g. "Hello" or "java.lang.StringBuilder").
 * Returns a JniClass handle.
 */
function jni_find_class(string $className): mixed
{

}

/**
 * Find a method by name. Returns a JniMethod handle that caches
 * the jmethodID and type signatures. Handles method overloading;
 * the correct overload is selected at call time by argument count.
 *
 * @param mixed  $objOrClass  JniClass handle (or JniObject to get its class)
 * @param string $methodName  Java method name
 * @return mixed              JniMethod handle
 */
function jni_find_method(mixed $objOrClass, string $methodName): mixed
{

}

/**
 * Find a field by name. Returns a JniField handle that caches
 * the jfieldID and type signature.
 *
 * @param mixed  $objOrClass  JniClass handle (or JniObject to get its class)
 * @param string $fieldName   Java field name
 * @return mixed              JniField handle
 */
function jni_find_field(mixed $objOrClass, string $fieldName): mixed
{

}

/**
 * Create a new Java object. The constructor is found automatically
 * by matching argument count.
 *
 * @param mixed $classHandle  A JniClass handle from jni_find_class()
 * @param array $args         Constructor arguments
 * @return mixed              JniObject handle
 */
function jni_new_object(mixed $classHandle, array $args = []): mixed
{

}

/**
 * Call a Java method (instance or static).
 *
 * @param mixed $objOrClass  JniObject (instance method) or JniClass (static method)
 * @param mixed $method      JniMethod handle from jni_find_method()
 * @param array $args        Method arguments
 * @return mixed             Return value (string/int/bool/JniObject, or null for void)
 */
function jni_call(mixed $objOrClass, mixed $method, array $args = []): mixed
{

}

/**
 * Read a Java field (instance or static).
 *
 * @param mixed $objOrClass  JniObject (instance field) or JniClass (static field)
 * @param mixed $field       JniField handle from jni_find_field()
 * @return mixed             Field value
 */
function jni_get(mixed $objOrClass, mixed $field): mixed
{

}

/**
 * Write a Java field (instance or static).
 *
 * @param mixed $objOrClass  JniObject (instance field) or JniClass (static field)
 * @param mixed $field       JniField handle from jni_find_field()
 * @param mixed $value       New value to assign
 */
function jni_set(mixed $objOrClass, mixed $field, mixed $value): void
{

}
