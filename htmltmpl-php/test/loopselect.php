<?
$TEST = "loopselect";
require("head.inc");

#######################################################

$tproc->set("title", "Template world.");
$tproc->set("greeting", "Hello !");
$tproc->set("Boys", array(
    array( "name" => "Tomas",  "age" => 19,
        "friends" => array(
            array("value" => "tomas", "text" => "Tomas Styblo"),
            array("value" => "pavel", "text" => "Pavel Hejtman")
        )),
    array( "name" => "Pavel",  "age" => 34,
        "friends" => array(
            array("value" => "michal", "text" => "Michal Tukan"),
            array("value" => "petr", "text" => "Petr Novak")
        )),
));

#######################################################

require("foot.inc");

?>
