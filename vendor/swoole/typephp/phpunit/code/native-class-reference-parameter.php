<?php

#[Native]
class NativeReferenceParameterValue {}

function invalidNativeReferenceParameter(NativeReferenceParameterValue &$value): void {}
