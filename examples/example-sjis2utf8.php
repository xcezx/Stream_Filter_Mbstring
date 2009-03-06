<?php

require_once 'Stream/Filter/Mbstring.php';

$ret = stream_filter_register("convert.mbstring.*", "Stream_Filter_Mbstring");

if (isset($argv[1])) {
  $file = $argv[1];
} else {
  $file = 'php://stdin';
}
$fp = fopen($file, 'r');
if ($fp === false) {
    die("Could not open file: $file\n");
}
$filter_name = 'convert.mbstring.encoding.SJIS-win:UTF-8';
$filter = stream_filter_append($fp, $filter_name, STREAM_FILTER_READ);
if ($filter === false) {
    fclose($fp);
    die("Counld not apply stream filter: $filter_name\n");
}
$filter_name = 'convert.mbstring.kana.KVa:UTF-8';
$filter = stream_filter_append($fp, $filter_name, STREAM_FILTER_READ);
if ($filter === false) {
    fclose($fp);
    die("Counld not apply stream filter: $filter_name\n");
}
while (($line = fgets($fp)) !== false) {
    echo $line;
}
fclose($fp);
