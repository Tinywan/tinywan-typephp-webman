<?php

final class NamespaceAliasResolutionTest extends \BaseTest
{
    public function testExplicitAliasDoesNotMakeTheOriginalShortNameAvailable(): void
    {
        $this->expectException(\TypePhp\Exception\TestError::class);
        $this->expectExceptionMessage(
            'Class `Child` inherits from a non-existent class `AliasResolution\\Consumer\\Notes`',
        );

        $this->compile('namespace/explicit-alias-original-name.php');
    }
}
