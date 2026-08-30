--TEST--
ref call arg
--FILE--
<?php
function main()
{
    $path = tempnam(sys_get_temp_dir(), 'typephp-ref-');
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    for ($idx = 0; $idx < 4; $idx++) {
        $zip->addFromString('entry-' . $idx, 'data');
        $zip->setExternalAttributesIndex($idx, ZipArchive::OPSYS_UNIX, 0100644 << 16);
    }
    $zip->close();

    if ($zip->open($path) === TRUE) {
        for ($idx = 0; $s = $zip->statIndex($idx); $idx++) {
            $rs = $zip->getExternalAttributesIndex($idx, $opsys, $attr);
            var_dump($rs, $idx, $opsys, $attr);
        }
        $zip->close();
        echo "OK\n";
    }
    unlink($path);

    $str = "first=value&arr[]=foo+bar&arr[]=baz";
    parse_str($str, $output);
    echo $output['first'], PHP_EOL;  // value
    echo $output['arr'][0], PHP_EOL; // foo bar
    echo $output['arr'][1], PHP_EOL; // baz
    echo "DONE\n";
}
?>
--EXPECT--
bool(true)
int(0)
int(3)
int(2175008768)
bool(true)
int(1)
int(3)
int(2175008768)
bool(true)
int(2)
int(3)
int(2175008768)
bool(true)
int(3)
int(3)
int(2175008768)
OK
value
foo bar
baz
DONE
