<?
$TEST = "optimspace";
require('../htmltmpl.php');
if (in_array('debug', $argv)) {
    $HTMLTMPL_DEBUG = 'debug.log';
}

$man = new TemplateManager(TRUE, 5, FALSE, TRUE, FALSE, TRUE);
$template =& $man->prepare($TEST.'.tmpl');
$tproc = new TemplateProcessor(TRUE, TRUE, FALSE, NULL);
$output = '';

#######################################################

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

#######################################################

require("foot.inc");

?>
