<?php

/** @generated from examples/python/version.py */
use python\sys;

function main(): void
{
    if (sys\version_info < python\tuple([3, 8])) {
        echo '此脚本需要 Python 3.8 或更高版本', "\n";
        exit(1);
    } else {
        echo '当前 Python 版本: ' .
            sys\version_info->major->toString() . '.' .
            sys\version_info->minor->toString() . '.' .
            sys\version_info->micro->toString(), "\n";
    }
}
