<?

$TEST = "complexbig";
require("head.inc");

#######################################################

$tproc->set("title", "Hello template world.");
$tproc->set("blurb", 1);

$users = array(
    array( "name" => "Joe User", "age" => 18, "city" => "London", 
      "Skills" => array(
        array( "skill" => "computers" ),
        array( "skill" => "machinery" )
    )),
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Jack Newman", "age" => 21, "city" => "Moscow", 
      "Skills" => array(
        array( "skill" => "guitar" ),
        array( "skill" => "piano" ),
        array( "skill" => "flute" )        
    )),
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
  array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    
   array( "name" => "Peter Nobody", "age" => 35, "city" => "Paris", 
      "Skills" => array(
        array( "skill" => "tennis" ),
        array( "skill" => "football" ),
        array( "skill" => "baseball" ),
        array( "skill" => "fishing" )
    )),    

);

$tproc->set("Users", $users);

$products = array(
    array( "key" => 12, "name" => "cake",  "selected" => 0 ),
    array( "key" => 45, "name" => "milk",  "selected" => 1 ),
    array( "key" => 78, "name" => "pizza", "selected" => 0 ),
    array( "key" => 32, "name" => "roll",  "selected" => 0 ),
    array( "key" => 98, "name" => "ham",   "selected" => 0 )
);

$tproc->set("Products", $products);
$tproc->set("Unused_loop", array());

#######################################################

require("foot.inc");

?>
