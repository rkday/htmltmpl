<?

$TEST = "escape";
require("head.inc");

#######################################################

$tproc->set("title", "Template world.");
$tproc->set("greeting", "Hello !");
$tproc->set("data", '<TAG PARAM="foo"> &entita; </TAG>');

#######################################################

require("foot.inc");

?>
