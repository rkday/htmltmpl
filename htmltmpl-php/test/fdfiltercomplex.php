<?

$TEST = "fdfiltercomplex";
require("head.inc");

#######################################################

$tproc->set("title", "Hello template world.");
$tproc->set("blurb", 1);

$users = array(
    array( "name" => "Joe User", "age" => 18, "city" => "London", 
      "Skills" => array(
        array( "skill" => "computers" ),
        array( "skill" => "machinery" )
    )),
    array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
    array( "name" => "Jack Newman", "age" => 21, "city" => "Moscow", 
      "Skills" => array(
        array( "skill" => "guitar" ),
        array( "skill" => "piano" ),
        array( "skill" => "flute" )        
    ))
);

$tproc->set("Users", $users);

$products = array(
    array( "key" => 12, "name" => "cake",  "selected" => 0 ),
    array( "key" => 45, "name" => "milk",  "selected" => 1 ),
    array( "key" => 78, "name" => "pizza", "selected" => 0 ),
    array( "key" => 32, "name" => "roll",  "selected" => 0 ),
    array( "key" => 98, "name" => "ham",   "selected" => 0 )
);

$tproc->set("Products", $products);
$tproc->set("Unused_loop", array());

#######################################################

if (! ($fh = fopen("$TEST.fd", "wb"))) {
    echo "FAILED";
    exit (1);
}

$start = gettimeofday();
$tproc->process($template, NULL, TRUE, "__my_filter", $fh);
$elapsed = HtmltmplUtil::gettimeofday_diff_ms($start);
fclose($fh);

$resx_file = fopen("$TEST.fd", "rb");
$resx = fread($resx_file, filesize("$TEST.fd"));
fclose($resx_file);

$res_file = fopen("$TEST.res", "rb");
$res = fread($res_file, filesize("$TEST.res"));
fclose($res_file);

unlink("$TEST.fd");

echo $TEST, ' ... ';

if ($resx == $res) {
    echo sprintf("OK ... %d ms", $elapsed);
}
else {
    echo 'FAILED';
    $fail_file = fopen("$TEST.fail", 'w');
    fputs($fail_file, $resx);
    fclose($fail_file);
}

function __my_filter($data) {
    return (strtoupper($data));
}

?>
