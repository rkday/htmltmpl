<?
$start = gettimeofday();
$output .= $tproc->process($template);
$elapsed = HtmltmplUtil::gettimeofday_diff_ms($start);

if (in_array('out', $argv)) {
    echo $output;
    exit();
}

$res_file = fopen("$TEST.res", 'r');
$res = fread($res_file, filesize("$TEST.res"));
fclose($res_file);

echo $TEST, ' ... ';

if ($output == $res) {
    echo sprintf("OK ... %d ms", $elapsed);
}
else {
    echo 'FAILED';
    $fail_file = fopen("$TEST.fail", 'w');
    fputs($fail_file, $output);
    fclose($fail_file);
}

?>
