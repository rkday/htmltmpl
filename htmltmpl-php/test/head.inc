<?
require('../htmltmpl.php');

if (in_array('debug', $argv)) {
    $HTMLTMPL_DEBUG = 'debug.log';
}

$man = new TemplateManager(TRUE, 5, FALSE, TRUE);
$template =& $man->prepare($TEST.'.tmpl');
$tproc = new TemplateProcessor();
$output = ''
?>
