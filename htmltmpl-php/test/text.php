<?
$TEST = "text";
require("head.inc");

#######################################################

$tproc->set("title", "Template world.");
$tproc->set("greeting", "Hello !");
$tproc->set("user", "tomas");

#######################################################

require("foot.inc");

?>
