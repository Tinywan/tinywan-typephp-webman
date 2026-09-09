<?php
function isAsciiWhitespace(int $code) {
    // 确保参数在有效范围内
    if (!is_int($code) || $code < 0 || $code > 127) {
        throw new InvalidArgumentException('参数必须是0-127之间的整数');
    }

    // 使用chr()将ASCII码转换为字符，然后用ctype_space检测
    $char = chr($code);
    return ctype_space($char);
}

function main()
{
    $list = [9, 10, 12, 13, 32];
    foreach ($list as $code) {
        if (isAsciiWhitespace($code)) {
            echo "The ASCII code $code is a whitespace character.\n";
        } else {
            echo "The ASCII code $code is not a whitespace character.\n";
        }
    }
}