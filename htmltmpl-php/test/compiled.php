<?

$TEST = "compiled";
if (in_array('debug', $argv)) {
    $HTMLTMPL_DEBUG="debug.log";
}
require('../htmltmpl.php');
$man = new TemplateManager();
$template =& $man->prepare("$TEST.tmpl");
$tproc = new TemplateProcessor();

#######################################################

function fill(&$tproc) {
    $tproc->set("title", "Template world.");
    $tproc->set("greeting", "Hello !");
    $tproc->set("Boys", array(
        array( "name" => "Tomas",  "age" => 19 ),
        array( "name" => "Pavel",  "age" => 34 ),
        array( "name" => "Janek",  "age" => 67 ),
        array( "name" => "Martin", "age" => 43 ),
        array( "name" => "Viktor", "age" => 78 ),
        array( "name" => "Marian", "age" => 90 ),
        array( "name" => "Prokop", "age" => 23 ),
        array( "name" => "Honzik", "age" => 46 ),
        array( "name" => "Brudra", "age" => 64 ),
        array( "name" => "Marek",  "age" => 54 ),
        array( "name" => "Peter",  "age" => 42 ),
        array( "name" => "Beda",   "age" => 87 )
    ));
}

#######################################################

fill($tproc);
$tproc->process($template);
$tproc->reset();

fill($tproc);
$tproc->process($template);
$tproc->reset();

fill($tproc);
$output = $tproc->process($template);

if (in_array('out', $argv)) {
    echo $output;
    exit;
}

$file_res = fopen("$TEST.res", 'r');
$res = fread($file_res, filesize("$TEST.res"));
fclose($file_res);

echo "$TEST ... ";

if ($output == $res && is_file("$TEST.tmplc")) {
    echo "OK";
    unlink("$TEST.tmplc");
    unlink("$TEST.tmplcc");
}
else {
    echo "FAILED";
}
?>
