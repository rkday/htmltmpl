<?

$TEST = "globalvars";
require("head.inc");

#######################################################

$tproc->set("title", "Template world.");
$tproc->set("greeting", "Hello !");
$tproc->set("Loop1", array( array() ));

#######################################################

require("foot.inc");

?>
