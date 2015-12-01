<?php

if (!isset($argv[1])) {
    echo "\n";
    echo "SYNTAX:\n";
    $self = $argv[0];
    echo "	php $self <log_file>\n";
    echo "\n";
    exit;
}

$outputFile = 'reqs_by_sec.csv';

$startTime = microtime(true);
$file = $argv[1];
$handle = fopen($file, "r");

if (!$handle) 
	die("Cannot open $file\n");

$totalLines = 0;
$reqsBySec = [];
$maxReqsPerSec = 0;
$maxReqsPerSecTime = null;

while (!feof($handle)) {
    $lineData = fgetcsv($handle, 4096, ' ');

    if (!is_array($lineData)) break;
//    var_dump($lineData);
    $data = [];
    mapCsvColumn($lineData, 0, $data, 'ip');
    mapCsvColumn($lineData, 3, $data, 'timePart1');
    mapCsvColumn($lineData, 4, $data, 'timePart2');
    mapCsvColumn($lineData, 5, $data, 'request');
    mapCsvColumn($lineData, 6, $data, 'httpCode');
    mapCsvColumn($lineData, 8, $data, 'referal');
    mapCsvColumn($lineData, 9, $data, 'userAgent');

    $timeStr = $data['timePart1'] . ' ' . $data['timePart2'];
    $timeStr = str_replace(['[',']'], '', $timeStr);
    $timeStrUnix = strtotime($timeStr);
    $timeStr = date('Y-m-d H:i:s', $timeStrUnix);

    $data['time'] = $timeStr;
    $data['timeUnix'] = $timeStrUnix;

    unset($data['timePart1']);
    unset($data['timePart2']);

    // var_dump($data);die;

    if (!isset($reqsBySec[$timeStr]))
        $reqsBySec[$timeStr] = 0;
    $reqsBySec[$timeStr]++;
    if ($maxReqsPerSec < $reqsBySec[$timeStr]) {
        $maxReqsPerSec = $reqsBySec[$timeStr];
        $maxReqsPerSecTime = $timeStr;
    }

    $totalLines++;

    // if ($totalLines >= 10) break;
}
ksort($reqsBySec);
// var_dump($reqsBySec);die;
fclose($handle);

// write output at around +/- 5 mins
$maxReqsPerSecTimeUnix = strtotime($maxReqsPerSecTime);
$fromTimeUnix = strtotime('-5 minute', $maxReqsPerSecTimeUnix);
$toTimeUnix   = strtotime('+5 minute', $maxReqsPerSecTimeUnix);

$handle = fopen($outputFile, 'w+');
fwrite($handle, "Time,Requests\n");
foreach ($reqsBySec as $time => $count) {
    $timeUnix = strtotime($time);
    if ($timeUnix>=$fromTimeUnix && $timeUnix<=$toTimeUnix) {
        fwrite($handle, "\"$time\",$count\n");
    }
}
fclose($handle);

echo "\nPeak: $maxReqsPerSec reqs/s at $maxReqsPerSecTime\n";
echo "Output +/- 5 mins around peak time to: $outputFile\n";
echo "\n";
echo "Total lines: $totalLines\n";
echo "Elapsed: ".(microtime(true)-$startTime)."\n";
echo "Memory: ".floor(memory_get_peak_usage()/1024/0124)."MB\n";

function mapCsvColumn($csvData, $colIndex, &$data, $colName) {
    if (count($csvData) >= $colIndex) {
        $data[$colName] = $csvData[$colIndex];
    }

    return $data;
}
