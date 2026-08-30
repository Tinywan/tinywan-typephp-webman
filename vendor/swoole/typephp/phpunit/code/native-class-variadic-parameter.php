<?php

#[Native]
class NativeVariadicParameterValue {}

function invalidNativeVariadicParameter(NativeVariadicParameterValue ...$values): void {}
