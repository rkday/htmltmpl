<?

$TEST = "cached";
require("head.inc");

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

require("foot.inc");

?>
