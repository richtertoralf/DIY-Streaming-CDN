<?php

function getSymbolByQuantity($bytes)
{
    $symbols = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB');
    $exp = floor(log($bytes) / log(1024));
    return sprintf('%.2f ' . $symbols[$exp], ($bytes / pow(1024, floor($exp))));
}

// Total disk space
$ds = disk_total_space("/var/www");
// Free disk space
$df = disk_free_space("/var/www");
// Disk space in use
$du = $ds - $df;
// Proportion of currently used disk space
$duPercent = number_format(round($du * 100 / $ds, 2), 2);

// Output as JSON
$arr = array(
    'disk_total_space' => getSymbolByQuantity($ds),
    'disk_free_space' => getSymbolByQuantity($df),
    'disk_use_space' => getSymbolByQuantity($du),
    'disk_use_space_pct' => $duPercent . ' %'
);
echo json_encode($arr);

// So you can display single values in the Linux terminal:
// curl -s 192.168.55.101/php/diskFree.php | jq -r .disk_use_space_pct

// Alternative output of individual values:
// echo ('disk_free_space: ' . getSymbolByQuantity($df) . "<br />");
// echo ('disk_total_space: ' . getSymbolByQuantity($ds) . "<br />");
// echo ('disk_use_space: ' . getSymbolByQuantity($du) . "<br />");
// echo ('disk_use_space: ' . $duPercent . " % <br />");
