<?php
// 1. 创建一个包含 10 个 int 的 C 风格数组
$size = 10;
$cArray = FFI::new("int[$size]");

// 2. 获取一个指向第 0 个元素的指针
$ptr = FFI::addr($cArray[0]);

// 3. 关键：将指针偏移 100000 个 int 的位置，然后写入
//    这完全超出了数组的实际边界（只有 10 个元素）
$offset = 100000;
FFI::memcpy(
    FFI::cast("int*", $ptr + $offset * FFI::sizeof(FFI::type("int"))),
    FFI::addr(FFI::new("int", false, 12345)), // 要写入的值
    FFI::sizeof(FFI::type("int"))
);

echo "如果看到这行，说明没崩（通常看不到）\n";
?>
