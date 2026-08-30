<?php

#[Native]
class NativeNullUnionParameterValue {}

function invalidNativeNullUnionParameter(NativeNullUnionParameterValue|null $value): void {}
