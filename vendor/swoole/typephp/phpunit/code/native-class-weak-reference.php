<?php

#[Native]
class NativeWeakReferenceBoundary {}

function native_weak_reference_boundary(): void
{
    $value = new NativeWeakReferenceBoundary();
    WeakReference::create($value);
}
