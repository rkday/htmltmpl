<?

$TEST = "nestloop";
require("head.inc");

#######################################################

$tproc->set("title", "Template world.");
$tproc->set("greeting", "Hello !");
$tproc->set("Loop1", array(
    array( "Loop2" => array( array(), array() ), 
           "Loop3" => array(), 
           "Loop4" => array( array( "Loop6" => array(
        array(), array(), array()
      ) ) ), 
           "Loop5" => array() ),

    array( "Loop2" => array(), 
      "Loop3" => array( array(), array() ), 
      "Loop4" => array(), 
      "Loop5" => array( array() ) )
));

#######################################################

require("foot.inc");

?>
