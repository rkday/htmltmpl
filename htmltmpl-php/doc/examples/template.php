<?
require('../../htmltmpl.php');

# Compile or load already precompiled template.
$manager = new TemplateManager();
$template =& $manager->prepare("template.tmpl");
$tproc = new TemplateProcessor();

# Set the title.
$tproc->set("title", "Our customers");

# Create the 'Customers' loop. (regular array)
$customers = array();

# First customer (associative array).
$customer = array();
$customer['name'] = 'Joe Sixpack';
$customer['city'] = 'Los Angeles';
$customer['new'] = 0;
array_push($customers, $customer);

# Second customer.
$customer = array();
$customer['name'] = 'Paul Newman';
$customer['city'] = 'New York';
$customer['new'] = 1;
array_push($customers, $customer);

$tproc->set("Customers", $customers);

# Print the processed template.
echo $tproc->process($template);
?>
