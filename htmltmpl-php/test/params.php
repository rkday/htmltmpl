<?

$TEST = "params";
require("head.inc");

#######################################################

$tproc->set("title", "Template world.");
$tproc->set("greeting", "Hello <HTML> world !");
$tproc->set("Loop", array( array() ));

#######################################################

require("foot.inc");

?>
