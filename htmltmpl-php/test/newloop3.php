<?
$TEST = "newloop3";
require("head.inc");

#######################################################

$tproc->set("title", "Template world.");
$tproc->set("greeting", "Hello !");

$data = array(
    array( "name" => "Tomas",  "age" => 19, 
        "friends" => array("Milan", "Pavel")),
    array( "name" => "Pavel",  "age" => 34,
        "friends" => array("Petr", "Standa")),
    array( "name" => "Janek",  "age" => 67,
        "friends" => array("Vladimir", "Boris")),
);

$boys = $tproc->loop("Boys", "name", "age", "Friends");
foreach ($data as $d) {
    $boys->push($d['name'], $d['age']);
    $friends = $tproc->loop("Friends", "name");
    foreach ($d['friends'] as $f) {
        $friends->push($f);
    }
    $boys->add($friends);
}
$boys->commit();

#######################################################

require("foot.inc");

?>
