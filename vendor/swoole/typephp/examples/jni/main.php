<?php

function main()
{
    // 1. 初始化 JVM
    echo "=== Step 1: Initialize JVM ===\n";
    jni_init(".");
    echo "JVM initialized.\n\n";

    // ---------------------------------------------------------------
    // 2. 动态创建自定义 Hello 对象、调用方法、读写属性
    // ---------------------------------------------------------------
    echo "=== Step 2: Dynamic Java Object (Hello) ===\n";

    // 查找类
    $helloClass = jni_find_class("Hello");
    echo "Found class: Hello\n";

    // 获取方法、属性（反射 + 缓存为 Box 类型）
    $greet = jni_find_method($helloClass, "greet");
    $nameField = jni_find_field($helloClass, "name");
    $ageField = jni_find_field($helloClass, "age");

    // 创建对象: Hello(String name, int age)
    $hello = jni_new_object($helloClass, ["Swoole", 8]);
    echo "Created Hello object.\n";

    // 调用方法: String greet(String greeting)
    $msg = jni_call($hello, $greet, ["你好"]);
    echo "greet() → " . $msg . "\n";

    // 读取属性
    $name = jni_get($hello, $nameField);
    $age = jni_get($hello, $ageField);
    echo "name = " . $name . ", age = " . $age . "\n";

    // 修改属性
    jni_set($hello, $nameField, "PHP");
    jni_set($hello, $ageField, 10);
    $name2 = jni_get($hello, $nameField);
    $age2 = jni_get($hello, $ageField);
    echo "After set: name = " . $name2 . ", age = " . $age2 . "\n";
    echo "greet() → " . jni_call($hello, $greet, ["Hi"]) . "\n\n";

    // ---------------------------------------------------------------
    // 3. 使用标准 Java 类 (StringBuilder)
    // ---------------------------------------------------------------
    echo "=== Step 3: Standard Java Class (StringBuilder) ===\n";

    $sbClass = jni_find_class("java.lang.StringBuilder");
    echo "Found class: java.lang.StringBuilder\n";

    // 获取方法
    $append = jni_find_method($sbClass, "append");
    $toString = jni_find_method($sbClass, "toString");

    // StringBuilder sb = new StringBuilder("Hello");
    $sb = jni_new_object($sbClass, ["Hello"]);
    echo "Created StringBuilder.\n";

    // sb.append(" Java")
    $sb2 = jni_call($sb, $append, [" Java"]);
    // sb.append(" JNI")
    jni_call($sb, $append, [" JNI"]);

    // sb.toString()
    $str = jni_call($sb, $toString, []);
    echo "toString() → " . $str . "\n\n";

    // ---------------------------------------------------------------
    // 4. 销毁 JVM
    // ---------------------------------------------------------------
    echo "=== Step 4: Destroy JVM ===\n";
    jni_destroy();
    echo "JVM destroyed.\n";
}
