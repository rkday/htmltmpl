<?
$TEST = "checkbox";
require("head.inc");

#######################################################

$tproc->set("title", "Template world.");
$tproc->set("greeting", "Hello !");
$tproc->set("boys", array(
    array( "text" => "Tomas",  "value" => 19 ),
    array( "text" => "Pavel",  "value" => 34 ),
    array( "text" => "Janek",  "value" => 67 ),
    array( "text" => "Martin", "value" => 43, "checked" => TRUE ),
    array( "text" => "Viktor", "value" => 78 ),
    array( "text" => "Marian", "value" => 90 ),
    array( "text" => "Prokop", "value" => 23 ),
    array( "text" => "Honzik", "value" => 46 ),
    array( "text" => "Brudra", "value" => 64, "checked" => TRUE ),
    array( "text" => "Marek",  "value" => 54 ),
    array( "text" => "Peter",  "value" => 42 ),
    array( "text" => "Beda",   "value" => 87 )
));

#######################################################

require("foot.inc");

?>
